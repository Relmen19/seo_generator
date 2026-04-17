const express = require('express');
const puppeteer = require('puppeteer-core');

const app = express();
app.use(express.json({ limit: '50mb' }));

const MAX_CONCURRENT = parseInt(process.env.MAX_CONCURRENT_PAGES || '3', 10);
let activeTasks = 0;
let browser = null;

async function getBrowser() {
    if (!browser || !browser.isConnected()) {
        browser = await puppeteer.launch({
            executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--single-process',
            ],
        });
    }
    return browser;
}

app.get('/health', (req, res) => {
    res.json({ status: 'ok', activeTasks });
});

app.post('/screenshot', async (req, res) => {
    if (activeTasks >= MAX_CONCURRENT) {
        return res.status(503).json({ error: 'Too many concurrent requests' });
    }

    const { html, width = 800, deviceScaleFactor = 2 } = req.body;

    if (!html) {
        return res.status(400).json({ error: 'html field is required' });
    }

    activeTasks++;
    let page = null;

    try {
        const b = await getBrowser();
        page = await b.newPage();

        await page.setViewport({
            width: parseInt(width, 10),
            height: 600,
            deviceScaleFactor: parseInt(deviceScaleFactor, 10),
        });

        await page.setContent(html, {
            waitUntil: 'networkidle0',
            timeout: 30000,
        });

        // Extra wait for CSS animations / chart rendering
        await new Promise(r => setTimeout(r, 500));

        // Get actual content height
        const bodyHandle = await page.$('body');
        const boundingBox = await bodyHandle.boundingBox();
        const contentHeight = Math.ceil(boundingBox.height);
        await bodyHandle.dispose();

        // Resize viewport to content
        await page.setViewport({
            width: parseInt(width, 10),
            height: contentHeight || 600,
            deviceScaleFactor: parseInt(deviceScaleFactor, 10),
        });

        const screenshot = await page.screenshot({
            type: 'png',
            fullPage: true,
            omitBackground: false,
        });

        const base64 = screenshot.toString('base64');

        res.json({
            image: base64,
            width: parseInt(width, 10) * parseInt(deviceScaleFactor, 10),
            height: contentHeight * parseInt(deviceScaleFactor, 10),
        });
    } catch (err) {
        console.error('Screenshot error:', err.message);
        res.status(500).json({ error: err.message });
    } finally {
        if (page) {
            try { await page.close(); } catch (_) {}
        }
        activeTasks--;
    }
});

const PORT = process.env.PORT || 3000;

getBrowser()
    .then(() => {
        app.listen(PORT, '0.0.0.0', () => {
            console.log(`Puppeteer service running on port ${PORT}`);
        });
    })
    .catch(err => {
        console.error('Failed to start browser:', err);
        process.exit(1);
    });

// Graceful shutdown
process.on('SIGTERM', async () => {
    if (browser) await browser.close();
    process.exit(0);
});
