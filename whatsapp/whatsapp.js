import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
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
                console.log(`[Assistant ${assistantId}] Procesando mensaje de ${msg.key.remoteJid}, fromMe=${msg.key.fromMe}`);
                if (!msg.key.fromMe && msg.message) {
                    const from = msg.key.remoteJid;
                    // Avoid processing group messages if not intended
                    if (from.endsWith('@g.us')) {
                        console.log(`[Assistant ${assistantId}] Ignorando mensaje de grupo: ${from}`);
                        continue;
                    }

                    // Log the structure to see what's coming
                    const msgType = Object.keys(msg.message)[0];
                    console.log(`[Assistant ${assistantId}] Tipo de mensaje detectado: ${msgType}`);

                    // Broad text extraction
                    let text = msg.message.conversation ||
                        msg.message.extendedTextMessage?.text ||
                        msg.message.buttonsResponseMessage?.selectedButtonId ||
                        msg.message.listResponseMessage?.singleSelectReply?.selectedRowId ||
                        msg.message.templateButtonReplyMessage?.selectedId ||
                        '';

                    // If it's an ephemeral message, extract from content
                    if (msgType === 'ephemeralMessage') {
                        text = msg.message.ephemeralMessage.message?.conversation ||
                            msg.message.ephemeralMessage.message?.extendedTextMessage?.text || '';
                    }

                    if (text) {
                        console.log(`[Assistant ${assistantId}] Mensaje extraído correctamente de ${from}: "${text}"`);

                        try {
                            // Dynamically resolve backend URL with current port
                            const rawBackendUrl = process.env.BACKEND_URL || 'http://localhost/message.php';
                            const envPort = process.env.PORT || '80';
                            const backendUrl = rawBackendUrl.replace('${PORT}', envPort);

                            console.log(`[Assistant ${assistantId}] Enviando a backend: ${backendUrl}`);

                            const formData = new FormData();
                            formData.append('text', text);
                            formData.append('assistant_id', assistantId);
                            // Security token for internal communication
                            const token = process.env.INTERNAL_TOKEN || 'local_secret_123';
                            formData.append('internal_token', token);

                            const response = await axios.post(backendUrl, formData, {
                                headers: formData.getHeaders(),
                                timeout: 35000 // 35s timeout
                            });

                            console.log(`[Assistant ${assistantId}] Respuesta del backend recibida. Status: ${response.status}`);

                            if (response.data && response.data.reply) {
                                console.log(`[Assistant ${assistantId}] Enviando respuesta a WhatsApp...`);
                                const cleanReply = response.data.reply
                                    .replace(/<br\s*\/?>/gi, '\n')
                                    .replace(/&nbsp;/g, ' ')
                                    .trim();
                                await sock.sendMessage(from, { text: cleanReply });
                            } else {
                                console.log(`[Assistant ${assistantId}] El backend no devolvió una respuesta válida:`, response.data);
                            }
                        } catch (error) {
                            console.error(`[Assistant ${assistantId}] Error crítico comunicando con backend:`, error.message);
                            if (error.response) {
                                console.error(`[Assistant ${assistantId}] Detalle del error del backend:`, error.response.data);
                            }
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
