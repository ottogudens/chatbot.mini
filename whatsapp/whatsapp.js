const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    jmp
} = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const P = require('pino');
const qrcode = require('qrcode-terminal');
const axios = require('axios');
const FormData = require('form-data');
require('dotenv').config();

const logger = P({ level: 'info' });

async function startWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Usando WaWeb v${version.join('.')}, isLatest: ${isLatest}`);

    const sock = makeWASocket({
        version,
        logger,
        printQRInTerminal: true,
        auth: state,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom) ? 
                lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut : true;
            console.log('Conexión cerrada debido a ', lastDisconnect.error, ', reconectando: ', shouldReconnect);
            if (shouldReconnect) {
                startWhatsApp();
            }
        } else if (connection === 'open') {
            console.log('Conexión abierta exitosamente');
        }
    });

    sock.ev.on('messages.upsert', async (m) => {
        if (m.type === 'notify') {
            for (const msg of m.messages) {
                if (!msg.key.fromMe && msg.message) {
                    const from = msg.key.remoteJid;
                    const text = msg.message.conversation || 
                                 msg.message.extendedTextMessage?.text || 
                                 '';

                    if (text) {
                        console.log(`Mensaje recibido de ${from}: ${text}`);
                        
                        try {
                            const formData = new FormData();
                            formData.append('text', text);
                            formData.append('assistant_id', process.env.DEFAULT_ASSISTANT_ID || '1');

                            const response = await axios.post(process.env.BACKEND_URL, formData, {
                                headers: formData.getHeaders()
                            });

                            if (response.data && response.data.reply) {
                                // Strip HTML tags like <br> which are added by nl2br in PHP
                                const cleanReply = response.data.reply.replace(/<br\s*\/?>/gi, '\n');
                                await sock.sendMessage(from, { text: cleanReply });
                            }
                        } catch (error) {
                            console.error('Error al procesar mensaje con el backend:', error.message);
                            // Optional: notify user on WhatsApp about the error
                        }
                    }
                }
            }
        }
    });
}

startWhatsApp();
