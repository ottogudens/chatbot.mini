import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    downloadMediaMessage,
} from '@whiskeysockets/baileys';

import { Boom } from '@hapi/boom';
import P from 'pino';
import express from 'express';
import cors from 'cors';
import axios from 'axios';
import FormData from 'form-data';
import QRCode from 'qrcode';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();

// SEC FIX: CORS restringido. Este bridge solo es llamado por el propio backend PHP
// (Nginx en Railway, mismo contenedor). CORS abierto (cors()) no tiene sentido
// para un servicio interno y expone endpoints a peticiones cross-origin arbitrarias.
// Permitimos solo llamadas desde localhost/127.0.0.1 o desde un APP_URL configurado.
const ALLOWED_ORIGINS = [
    'http://localhost',
    'http://127.0.0.1',
    process.env.APP_URL,
].filter(Boolean);

app.use(cors({
    origin: (origin, callback) => {
        // Permitir requests sin origin (Nginx interno, curl, PHP)
        if (!origin || ALLOWED_ORIGINS.some(o => origin.startsWith(o))) {
            return callback(null, true);
        }
        return callback(new Error('CORS: origin not allowed'));
    }
}));
app.use(express.json());

// SEC FIX: El token NO tiene fallback hardcodeado.
// Si INTERNAL_TOKEN no está configurado en Railway, el bridge arranca sin autenticación
// pero emitirá un warning claro en los logs. El fallback 'local_secret_123' del código
// original aparecía en el repositorio Git — cualquiera podía leerlo.
const BRIDGE_TOKEN = process.env.INTERNAL_TOKEN || '';
if (!BRIDGE_TOKEN) {
    console.warn('[SECURITY WARNING] INTERNAL_TOKEN no está configurado. El bridge acepta cualquier petición POST. Configura esta variable en Railway.');
}

// SEC: Helper para sanitizar el assistant ID (solo dígitos, sin path traversal)
function sanitizeId(id) {
    if (!id || !/^\d+$/.test(String(id))) return null;
    return String(id);
}

// SEC: Authentication middleware para endpoints mutantes
function authMiddleware(req, res, next) {
    if (req.method === 'POST') {
        const token = req.headers['x-internal-token'] || req.body?.internal_token || '';
        if (!BRIDGE_TOKEN || token !== BRIDGE_TOKEN) {
            return res.status(403).json({ status: 'error', message: 'Unauthorized' });
        }
    }
    next();
}

// SEC FIX: Rate limiter refactorizado.
// - req.connection.remoteAddress está deprecated en Node 18+; usar req.socket.remoteAddress.
// - Se añade límite de memory: limpiar entradas antiguas para evitar memory leak
//   en procesos de larga duración con muchas IPs únicas.
const rateLimitMap = new Map();
const RATE_WINDOW_MS = 60000; // 1 minuto
const RATE_MAX_REQS  = 60;
const RATE_MAX_ENTRIES = 5000; // evitar crecimiento ilimitado del Map

function rateLimiter(req, res, next) {
    // FIX: req.socket.remoteAddress reemplaza req.connection.remoteAddress (deprecated Node 18+)
    const ip = req.ip || req.socket?.remoteAddress || 'unknown';
    const now = Date.now();

    // Limpiar entradas expiradas si el Map crece demasiado
    if (rateLimitMap.size > RATE_MAX_ENTRIES) {
        for (const [key, entry] of rateLimitMap.entries()) {
            if (now - entry.start > RATE_WINDOW_MS) rateLimitMap.delete(key);
        }
    }

    if (!rateLimitMap.has(ip)) {
        rateLimitMap.set(ip, { count: 1, start: now });
        return next();
    }
    const entry = rateLimitMap.get(ip);
    if (now - entry.start > RATE_WINDOW_MS) {
        entry.count = 1;
        entry.start = now;
        return next();
    }
    entry.count++;
    if (entry.count > RATE_MAX_REQS) {
        return res.status(429).json({ status: 'error', message: 'Too many requests' });
    }
    next();
}

app.use(rateLimiter);
app.use(authMiddleware);

const logger = P({ level: 'silent' });
const sessions = {};
const qrData = {};

const AUTH_BASE_DIR = path.join(__dirname, 'sessions');
if (!fs.existsSync(AUTH_BASE_DIR)) {
    fs.mkdirSync(AUTH_BASE_DIR, { recursive: true });
}

async function startSession(assistantId) {
    if (sessions[assistantId]) return sessions[assistantId];

    const sessionDir = path.join(AUTH_BASE_DIR, `assistant_${assistantId}`);
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger,
        printQRInTerminal: false,
        auth: state,
    });

    sessions[assistantId] = sock;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            qrData[assistantId] = qr;
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom) ?
                lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut : true;

            console.log(`[Assistant ${assistantId}] Conexión cerrada. Reconectando: ${shouldReconnect}`);

            delete qrData[assistantId];
            if (shouldReconnect) {
                delete sessions[assistantId];
                startSession(assistantId);
            } else {
                delete sessions[assistantId];
                if (lastDisconnect.error?.output?.statusCode === DisconnectReason.loggedOut) {
                    fs.rmSync(sessionDir, { recursive: true, force: true });
                }
            }
        } else if (connection === 'open') {
            console.log(`[Assistant ${assistantId}] Conexión abierta`);
            delete qrData[assistantId];
        }
    });

    sock.ev.on('messages.upsert', async (m) => {
        console.log(`[Assistant ${assistantId}] Evento upsert recibido: type=${m.type}, count=${m.messages?.length}`);
        if (m.type === 'notify') {
            for (const msg of m.messages) {
                if (!msg.key.fromMe && msg.message) {
                    const from = msg.key.remoteJid;
                    // Ignorar mensajes de grupos
                    if (from.endsWith('@g.us')) {
                        continue;
                    }

                    const msgType = Object.keys(msg.message)[0];

                    const innerMsg =
                        msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.documentWithCaptionMessage?.message ||
                        msg.message;

                    const isAudio =
                        msgType === 'audioMessage' ||
                        msgType === 'pttMessage' ||
                        (innerMsg && (innerMsg.audioMessage || innerMsg.pttMessage)) !== undefined &&
                        (innerMsg?.audioMessage || innerMsg?.pttMessage) != null;

                    let text = msg.message.conversation ||
                        msg.message.extendedTextMessage?.text ||
                        '';

                    let interactiveId =
                        msg.message.buttonsResponseMessage?.selectedButtonId ||
                        msg.message.listResponseMessage?.singleSelectReply?.selectedRowId ||
                        msg.message.templateButtonReplyMessage?.selectedId ||
                        null;

                    if (interactiveId && !text) {
                        text = interactiveId;
                    }

                    if (msgType === 'ephemeralMessage') {
                        text = msg.message.ephemeralMessage.message?.conversation ||
                            msg.message.ephemeralMessage.message?.extendedTextMessage?.text || '';
                    }

                    if (text || isAudio) {
                        console.log(`[Assistant ${assistantId}] Mensaje de ${from}: ${isAudio ? "[AUDIO]" : `"${text}"`}`);

                        try {
                            // FIX: BACKEND_URL sin interpolación literal de string JS.
                            // El .env usa ${PORT} como placeholder custom que reemplazamos aquí.
                            // Si BACKEND_URL no está configurado, usamos el puerto del proceso.
                            const appPort = process.env.APP_PORT || process.env.PORT || '80';
                            const rawBackendUrl = process.env.BACKEND_URL || `http://localhost:${appPort}/message.php`;
                            const backendUrl = rawBackendUrl.replace('${PORT}', appPort);

                            const formData = new FormData();
                            formData.append('text', text);
                            formData.append('assistant_id', assistantId);
                            formData.append('remote_jid', from);
                            if (interactiveId) {
                                formData.append('interactive_id', interactiveId);
                            }
                            // SEC: INTERNAL_TOKEN sin fallback hardcodeado
                            formData.append('internal_token', BRIDGE_TOKEN);

                            if (isAudio) {
                                try {
                                    console.log(`[Assistant ${assistantId}] Descargando audio...`);
                                    const buffer = await downloadMediaMessage(
                                        msg,
                                        'buffer',
                                        {},
                                        {
                                            logger,
                                            reuploadRequest: sock.updateMediaMessage
                                        }
                                    );
                                    formData.append('audio', buffer, {
                                        filename: 'voice.ogg',
                                        contentType: 'audio/ogg; codecs=opus',
                                    });
                                } catch (downloadError) {
                                    console.error(`[Assistant ${assistantId}] Error descargando audio:`, downloadError.message);
                                }
                            }

                            const response = await axios.post(backendUrl, formData, {
                                headers: formData.getHeaders(),
                                // FIX: Timeout incrementado a 100s para cubrir el ciclo completo:
                                // audio download → Gemini upload (90s max) → PHP response → reply.
                                timeout: 100000
                            });

                            if (response.data && response.data.reply) {
                                const cleanReply = response.data.reply
                                    .replace(/<br\s*\/?>/gi, '\n')
                                    .replace(/&nbsp;/g, ' ')
                                    .trim();

                                if (response.data.type === 'buttons' && response.data.interactive) {
                                    await sock.sendMessage(from, {
                                        text: cleanReply,
                                        buttons: response.data.interactive.buttons,
                                        footer: response.data.interactive.footer ?? '',
                                        headerType: 1
                                    });
                                } else if (response.data.type === 'list' && response.data.interactive) {
                                    await sock.sendMessage(from, {
                                        text: cleanReply,
                                        sections: response.data.interactive.sections,
                                        title: response.data.interactive.title ?? '',
                                        buttonText: response.data.interactive.buttonText ?? 'Ver opciones'
                                    });
                                } else {
                                    await sock.sendMessage(from, { text: cleanReply });
                                }
                            }
                        } catch (error) {
                            console.error(`[Assistant ${assistantId}] Error backend:`, error.message);
                        }
                    }
                }
            }
        }
    });

    return sock;
}

// ─── REST API ─────────────────────────────────────────────────────────────────

// SEC FIX: Sanitizar :id en TODAS las rutas para prevenir Path Traversal.
// El id se usa para construir directorio: sessions/assistant_${id}
// Sin sanitización: /connect/../../etc podría navegar el filesystem.

app.get('/status/:id', async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.status(400).json({ status: 'error', message: 'Invalid ID' });

    const sock = sessions[id];
    if (!sock) return res.json({ status: 'disconnected' });

    const state = sock.user ? 'connected' : (qrData[id] ? 'connecting' : 'disconnected');
    res.json({ status: state });
});

app.get('/qr/:id', async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.status(400).json({ status: 'error', message: 'Invalid ID' });

    const qr = qrData[id];
    if (!qr) {
        const sock = sessions[id];
        if (sock && sock.user) return res.json({ status: 'connected' });
        return res.json({ status: 'no_qr' });
    }

    try {
        const qrBase64 = await QRCode.toDataURL(qr);
        res.json({ status: 'qr', qr: qrBase64 });
    } catch (err) {
        res.status(500).json({ status: 'error', message: 'Failed to generate QR' });
    }
});

app.post('/connect/:id', async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.status(400).json({ status: 'error', message: 'Invalid ID' });

    try {
        await startSession(id);
        res.json({ status: 'initializing' });
    } catch (err) {
        res.status(500).json({ status: 'error', message: err.message });
    }
});

app.post('/disconnect/:id', async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.status(400).json({ status: 'error', message: 'Invalid ID' });

    const sock = sessions[id];
    if (sock) {
        await sock.logout();
        delete sessions[id];
        delete qrData[id];
        res.json({ status: 'success' });
    } else {
        res.json({ status: 'not_connected' });
    }
});

app.post('/send/:id', async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.status(400).json({ status: 'error', message: 'Invalid ID' });

    const { to, text, mediaUrl, mediaType } = req.body;
    const sock = sessions[id];

    if (!sock || !sock.user) {
        return res.status(400).json({ status: 'error', message: 'Sesión no conectada o no lista' });
    }

    if (!to || (!text && !mediaUrl)) {
        return res.status(400).json({ status: 'error', message: 'Faltan parámetros (to, text/mediaUrl)' });
    }

    try {
        // SEC FIX: Validar que el número de teléfono tenga entre 7-15 dígitos (estándar E.164)
        // para prevenir que strings largos construyan JIDs inválidos o excesivos.
        const cleanNumber = to.replace(/\D/g, '');
        if (cleanNumber.length < 7 || cleanNumber.length > 15) {
            return res.status(400).json({ status: 'error', message: 'Número de teléfono inválido' });
        }
        const jid = `${cleanNumber}@s.whatsapp.net`;

        if (mediaUrl) {
            console.log(`[Assistant ${id}] Enviando media (${mediaType}) a ${jid}: ${mediaUrl}`);
            const sendOptions = { caption: text || '' };

            if (mediaType === 'image') {
                sendOptions.image = { url: mediaUrl };
            } else if (mediaType === 'video') {
                sendOptions.video = { url: mediaUrl };
            } else {
                const fileName = path.basename(mediaUrl.split('?')[0]) || 'documento';
                sendOptions.document = { url: mediaUrl };
                sendOptions.fileName = fileName;
                sendOptions.mimetype = 'application/octet-stream';
            }

            await sock.sendMessage(jid, sendOptions);
        } else {
            await sock.sendMessage(jid, { text });
        }
        res.json({ status: 'success' });
    } catch (err) {
        console.error(`[Assistant ${id}] Error enviando mensaje:`, err.message);
        res.status(500).json({ status: 'error', message: err.message });
    }
});

// Auto-start existing sessions on launch.
// FIX: Se restauran con un pequeño stagger (500ms entre cada una) para evitar
// que 20+ asistentes intenten conectar simultáneamente al boot y saturen
// los sockets de Baileys y el ancho de banda de WhatsApp.
if (fs.existsSync(AUTH_BASE_DIR)) {
    const dirs = fs.readdirSync(AUTH_BASE_DIR).filter(d => d.startsWith('assistant_'));
    dirs.forEach((dir, index) => {
        const id = dir.replace('assistant_', '');
        const safeId = sanitizeId(id);
        if (safeId) {
            setTimeout(() => {
                console.log(`Restaurando sesión para asistente ${safeId}...`);
                startSession(safeId);
            }, index * 500); // 500ms stagger entre sesiones
        }
    });
}

const WHATSAPP_SERVICE_PORT = parseInt(process.env.WHATSAPP_PORT || '3001', 10);
app.listen(WHATSAPP_SERVICE_PORT, '127.0.0.1', () => {
    console.log(`Servidor WhatsApp API ejecutándose internamente en puerto ${WHATSAPP_SERVICE_PORT}`);
});
