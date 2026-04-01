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
app.use(cors());
app.use(express.json());

// SEC: Authentication middleware for mutating endpoints
const BRIDGE_TOKEN = process.env.INTERNAL_TOKEN || '';
function authMiddleware(req, res, next) {
    // Only enforce on POST (mutating) endpoints
    if (req.method === 'POST') {
        const token = req.headers['x-internal-token'] || req.body?.internal_token || '';
        if (!BRIDGE_TOKEN || token !== BRIDGE_TOKEN) {
            return res.status(403).json({ status: 'error', message: 'Unauthorized' });
        }
    }
    next();
}

// SEC: Simple in-memory rate limiter (per IP, 60 requests per minute)
const rateLimitMap = new Map();
function rateLimiter(req, res, next) {
    const ip = req.ip || req.connection.remoteAddress;
    const now = Date.now();
    const windowMs = 60000; // 1 minute
    const maxReqs = 60;

    if (!rateLimitMap.has(ip)) {
        rateLimitMap.set(ip, { count: 1, start: now });
        return next();
    }
    const entry = rateLimitMap.get(ip);
    if (now - entry.start > windowMs) {
        entry.count = 1;
        entry.start = now;
        return next();
    }
    entry.count++;
    if (entry.count > maxReqs) {
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
    fs.mkdirSync(AUTH_BASE_DIR);
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
                // Remove session dir if logged out
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
                    // Avoid processing group messages if not intended
                    if (from.endsWith('@g.us')) {
                        continue;
                    }

                    // Log the structure to see what's coming
                    const msgType = Object.keys(msg.message)[0];

                    // M4: Detect audio in all its forms:
                    // - Direct: audioMessage
                    // - Ephemeral (disappearing): ephemeralMessage wrapping audioMessage
                    // - View-once voice: viewOnceMessage wrapping audioMessage
                    // - Forwarded: contextInfo.quotedMessage is not audio, but forwarded body may be
                    const innerMsg =
                        msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.documentWithCaptionMessage?.message ||
                        msg.message;

                    const isAudio =
                        msgType === 'audioMessage' ||
                        msgType === 'pttMessage' ||
                        (innerMsg && (innerMsg.audioMessage || innerMsg.pttMessage)) !== undefined && (innerMsg?.audioMessage || innerMsg?.pttMessage) != null;

                    // Broad text extraction
                    let text = msg.message.conversation ||
                        msg.message.extendedTextMessage?.text ||
                        '';

                    // Extract interactive response ID (Button/List selection)
                    let interactiveId = 
                        msg.message.buttonsResponseMessage?.selectedButtonId ||
                        msg.message.listResponseMessage?.singleSelectReply?.selectedRowId ||
                        msg.message.templateButtonReplyMessage?.selectedId ||
                        null;

                    // If it has an interactive ID but no text, use the ID as text for matching
                    if (interactiveId && !text) {
                        text = interactiveId;
                    }

                    // If it's an ephemeral message, extract from content
                    if (msgType === 'ephemeralMessage') {
                        text = msg.message.ephemeralMessage.message?.conversation ||
                            msg.message.ephemeralMessage.message?.extendedTextMessage?.text || '';
                    }

                    if (text || isAudio) {
                        console.log(`[Assistant ${assistantId}] Mensaje de ${from}: ${isAudio ? "[AUDIO]" : `"${text}"`}`);

                        try {
                            // Resolve backend URL:
                            // - APP_PORT = the Railway PORT (Nginx/PHP port, e.g. 8080)
                            // - BACKEND_URL can include ${PORT} as a placeholder, or use APP_PORT directly
                            const rawBackendUrl = process.env.BACKEND_URL || 'http://localhost:${PORT}/message.php';
                            const appPort = process.env.APP_PORT || process.env.PORT || '80';
                            const backendUrl = rawBackendUrl.replace('${PORT}', appPort);

                            const formData = new FormData();
                            formData.append('text', text);
                            formData.append('assistant_id', assistantId);
                            formData.append('remote_jid', from);
                            if (interactiveId) {
                                formData.append('interactive_id', interactiveId);
                            }
                            // Security token for internal communication
                            const token = process.env.INTERNAL_TOKEN || 'local_secret_123';
                            formData.append('internal_token', token);

                            if (isAudio) {
                                try {
                                    console.log(`[Assistant ${assistantId}] Descargando audio...`);
                                    const messageContent = msgType === 'ephemeralMessage' ? msg.message.ephemeralMessage.message : msg.message;
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
                                timeout: 45000 // Increased timeout for audio processing
                            });

                            if (response.data && response.data.reply) {
                                const cleanReply = response.data.reply
                                    .replace(/<br\s*\/?>/gi, '\n')
                                    .replace(/&nbsp;/g, ' ')
                                    .trim();
                                
                                if (response.data.type === 'buttons' && response.data.interactive) {
                                    // Send Buttons
                                    await sock.sendMessage(from, {
                                        text: cleanReply,
                                        buttons: response.data.interactive.buttons,
                                        footer: response.data.interactive.footer ?? '',
                                        headerType: 1
                                    });
                                } else if (response.data.type === 'list' && response.data.interactive) {
                                    // Send List
                                    await sock.sendMessage(from, {
                                        text: cleanReply,
                                        sections: response.data.interactive.sections,
                                        title: response.data.interactive.title ?? '',
                                        buttonText: response.data.interactive.buttonText ?? 'Ver opciones'
                                    });
                                } else {
                                    // Default Text
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

// REST API
app.get('/status/:id', async (req, res) => {
    const id = req.params.id;
    const sock = sessions[id];

    if (!sock) {
        return res.json({ status: 'disconnected' });
    }

    const state = sock.user ? 'connected' : (qrData[id] ? 'connecting' : 'disconnected');
    res.json({ status: state });
});

app.get('/qr/:id', async (req, res) => {
    const id = req.params.id;
    const qr = qrData[id];

    if (!qr) {
        const sock = sessions[id];
        if (sock && sock.user) {
            return res.json({ status: 'connected' });
        }
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
    const id = req.params.id;
    try {
        await startSession(id);
        res.json({ status: 'initializing' });
    } catch (err) {
        res.status(500).json({ status: 'error', message: err.message });
    }
});

app.post('/disconnect/:id', async (req, res) => {
    const id = req.params.id;
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
    const id = req.params.id;
    const { to, text, mediaUrl, mediaType } = req.body;
    const sock = sessions[id];

    if (!sock || !sock.user) {
        return res.status(400).json({ status: 'error', message: 'Sesión no conectada o no lista' });
    }

    if (!to || (!text && !mediaUrl)) {
        return res.status(400).json({ status: 'error', message: 'Faltan parámetros (to, text/mediaUrl)' });
    }

    try {
        const cleanNumber = to.replace(/\D/g, '');
        const jid = `${cleanNumber}@s.whatsapp.net`;

        if (mediaUrl) {
            console.log(`[Assistant ${id}] Enviando media (${mediaType}) a ${jid}: ${mediaUrl}`);
            const sendOptions = { caption: text || '' };

            if (mediaType === 'image') {
                sendOptions.image = { url: mediaUrl };
            } else if (mediaType === 'video') {
                sendOptions.video = { url: mediaUrl };
            } else {
                // Default to document for everything else
                const fileName = path.basename(mediaUrl.split('?')[0]) || 'documento';
                sendOptions.document = { url: mediaUrl };
                sendOptions.fileName = fileName;
                sendOptions.mimetype = 'application/octet-stream'; // Let Baileys/WhatsApp handle it or refine
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

// Auto-start existing sessions on launch
if (fs.existsSync(AUTH_BASE_DIR)) {
    const dirs = fs.readdirSync(AUTH_BASE_DIR);
    dirs.forEach(dir => {
        if (dir.startsWith('assistant_')) {
            const id = dir.replace('assistant_', '');
            console.log(`Restaurando sesión para asistente ${id}...`);
            startSession(id);
        }
    });
}

const WHATSAPP_SERVICE_PORT = 3001;
app.listen(WHATSAPP_SERVICE_PORT, '127.0.0.1', () => {
    console.log(`Servidor WhatsApp API ejecutándose internamente en puerto ${WHATSAPP_SERVICE_PORT}`);
});
