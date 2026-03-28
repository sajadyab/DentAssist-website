const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');
const http = require('http');

const PORT = Number(process.env.WHATSAPP_SERVER_PORT || 3210);
const HOST = process.env.WHATSAPP_SERVER_HOST || '127.0.0.1';
const QR_SCAN_TIMEOUT_MS = 300000;
const qrOutputPath = path.join(__dirname, 'last-qr.txt');

let isReady = false;
let qrTimeout = null;



let queue = [];
let processingQueue = false;

/** a Message Queue (prevents WhatsApp blocking)  */
async function processQueue() {

    if (processingQueue) return;
    if (queue.length === 0) return;
    if (!isReady) return;

    processingQueue = true;

    const job = queue.shift();

    try {

        const to = job.phone.replace(/\D/g, '') + '@c.us';

        await client.sendMessage(to, job.message);

        job.resolve(true);

    } catch (err) {

        job.reject(err);

    }

    processingQueue = false;

    setTimeout(processQueue, 1500); // delay reduces ban risk
}

const client = new Client({
    authStrategy: new LocalAuth({
        clientId: 'reminder-system'
    }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check'
        ]
    }
});

function sendJson(res, statusCode, payload) {
    res.writeHead(statusCode, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(payload));
}

client.on('qr', (qr) => {
    console.log('Scan this QR with WhatsApp:');
    qrcode.generate(qr, { small: true });

    qrcode.generate(qr, { small: true }, (qrAscii) => {
        try {
            fs.writeFileSync(
                qrOutputPath,
                'Scan this QR in WhatsApp > Linked Devices\n\n' + qrAscii + '\n',
                'utf8'
            );
            console.log('QR also saved to:', qrOutputPath);
        } catch (err) {
            console.log('Could not save QR file:', err?.message || err);
        }
    });

    if (qrTimeout) {
        clearTimeout(qrTimeout);
    }
    qrTimeout = setTimeout(() => {
        console.log('QR scan timeout. Restart the server to generate a new QR.');
    }, QR_SCAN_TIMEOUT_MS);
});

client.on('ready', () => {
    isReady = true;
    if (qrTimeout) {
        clearTimeout(qrTimeout);
    }
    console.log('WhatsApp connected. Service is ready.');
});

client.on('auth_failure', (msg) => {
    isReady = false;
    console.log('Authentication failure:', msg);
});

client.on('disconnected', (reason) => {

    isReady = false;

    console.log('Client disconnected:', reason);

    console.log('Reconnecting WhatsApp in 5 seconds...');

    setTimeout(() => {

        try {
            client.initialize();
        } catch (err) {
            console.log("Reconnect failed:", err?.message || err);
        }

    }, 5000);

});

const server = http.createServer(async (req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        return sendJson(res, 200, {
            ok: true,
            ready: isReady,
            queue: queue.length
        });
    }

    if (req.method === 'POST' && req.url === '/send') {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk.toString();
        });

        req.on('end', async () => {
            let payload = null;
            try {
                payload = JSON.parse(body);
            } catch (_) {
                return sendJson(res, 400, { ok: false, error: 'Invalid JSON body.' });
            }

            const phone = String(payload?.phone || '').trim();
            const message = String(payload?.message || '').trim();
            const to = phone.replace(/\D/g, '') + '@c.us';

            if (!phone || !message) {
                return sendJson(res, 422, { ok: false, error: 'phone and message are required.' });
            }

            if (!isReady) {
                return sendJson(res, 503, { ok: false, error: 'WhatsApp client is not ready yet.' });
            }

            try {
                const promise = new Promise((resolve, reject) => {

                    queue.push({
                        phone,
                        message,
                        resolve,
                        reject
                    });
                
                });
                
                processQueue();
                
                try {
                
                    await promise;
                
                    return sendJson(res, 200, { ok: true, queued: true });
                
                } catch (err) {
                
                    return sendJson(res, 500, {
                        ok: false,
                        error: err?.message || 'sendMessage failed'
                    });
                
                }
            } catch (err) {
                return sendJson(res, 500, {
                    ok: false,
                    error: err?.message || 'sendMessage failed'
                });
            }
        });
        return;
    }

    return sendJson(res, 404, { ok: false, error: 'Not found' });
});

server.listen(PORT, HOST, () => {
    console.log(`WhatsApp local server listening on http://${HOST}:${PORT}`);
});

client.initialize();