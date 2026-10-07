#!/usr/bin/env node
// Headless visitors for a local site, so the Interactions tab has something
// to show. Each one loads the page, scrolls part or all of the way, clicks a
// few controls, sometimes hammers one or touches a form, and leaves.
//
//   node bin/visitors.mjs https://site.test/ --visits 30 --device desktop --device mobile
//
// Playwright is resolved from the directory it runs in, so install it in the app:
//   npm install --save-dev playwright && npx playwright install chromium

import { createRequire } from 'node:module';
import { join } from 'node:path';
import { parseArgs } from 'node:util';

const { values, positionals } = parseArgs({
    allowPositionals: true,
    options: {
        visits: { type: 'string', default: '20' },
        device: { type: 'string', multiple: true, default: ['desktop', 'tablet', 'mobile'] },
    },
});

const url = positionals[0];

if (!url) {
    console.error('Usage: visitors.mjs <url> [--visits 20] [--device desktop --device mobile]');
    process.exit(1);
}

let chromium;

try {
    ({ chromium } = createRequire(join(process.cwd(), 'package.json'))('playwright'));
} catch {
    console.error('Playwright is not installed here. In the app run:\n  npm install --save-dev playwright\n  npx playwright install chromium');
    process.exit(1);
}

// A desktop user agent: a headless one says HeadlessChrome, and Footprint drops bots.
const DEVICES = {
    desktop: {
        viewport: { width: 1440, height: 900 },
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    },
    tablet: {
        viewport: { width: 820, height: 1180 },
        userAgent: 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        isMobile: true,
        hasTouch: true,
    },
    mobile: {
        viewport: { width: 390, height: 844 },
        userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
        isMobile: true,
        hasTouch: true,
        deviceScaleFactor: 2,
    },
};

const CONTROLS = 'a[href], button, summary, [role="button"], [role="tab"], [data-footprint]';
const FIELDS = 'form input:not([type="hidden"]):not([type="submit"]):not([type="button"]), form textarea, form select';

const pause = (from, to) => new Promise((resolve) => setTimeout(resolve, from + Math.random() * (to - from)));
const chance = (share) => Math.random() < share;
const pick = (items) => items[Math.floor(Math.random() * items.length)];

const origin = new URL(url).origin;
const devices = values.device.filter((device) => DEVICES[device]);
const total = Math.max(1, Number.parseInt(values.visits, 10) || 20);
const browser = await chromium.launch({ args: [`--user-agent=${DEVICES.desktop.userAgent}`] });

for (let visit = 1; visit <= total; visit++) {
    const device = devices[visit % devices.length];
    const context = await browser.newContext({ ...DEVICES[device], ignoreHTTPSErrors: true });

    // The tracker records nothing for an automated browser.
    await context.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false, configurable: true }));

    const page = await context.newPage();

    try {
        await page.goto(url, { waitUntil: 'load' });
        await pause(800, 2000);
        await wander(page);
        console.log(`${visit}/${total} ${device}`);
    } catch (error) {
        console.error(`${visit}/${total} ${device}: ${error.message.split('\n')[0]}`);
    } finally {
        // Leaving the page unloads it, which sends the last batch; closing the
        // context straight away would drop that beacon.
        await page.goto('about:blank').catch(() => {});
        await pause(400, 600);
        await context.close();
    }
}

await browser.close();
console.log(`Sent ${total} visits to ${url}. Open its entry in the control panel to see them.`);

async function wander(page) {
    const depth = chance(0.35) ? 0.2 + Math.random() * 0.4 : 1;
    const height = await page.evaluate(() => document.documentElement.scrollHeight - innerHeight);

    if (chance(0.6)) await clickSomething(page);

    for (let y = 0; y < height * depth; y += 250 + Math.random() * 350) {
        if (page.url().split('#')[0] !== url.split('#')[0]) return;

        await page.mouse.wheel(0, 300);
        await pause(250, 900);

        if (chance(0.2)) await clickSomething(page);
    }

    if (chance(0.25)) await touchForm(page);
    if (chance(0.1)) await hammer(page);

    await pause(500, 1500);
}

// Clicks a visible control that stays on this site and does not send a form.
async function clickSomething(page) {
    const controls = (await page.locator(CONTROLS).all()).slice(0, 200);
    const visible = [];

    for (const control of controls) {
        const ok = await control
            .evaluate((element, origin) => {
                const box = element.getBoundingClientRect();
                const href = element.closest('a')?.href;

                return box.width > 0 && box.bottom > 0 && box.top < innerHeight && !element.closest('form') && (!href || new URL(href).origin === origin);
            }, origin)
            .catch(() => false);

        if (ok) visible.push(control);
    }

    if (visible.length > 0) {
        await pick(visible).click({ timeout: 2000, noWaitAfter: true }).catch(() => {});
        await pause(600, 1500);
    }
}

// Focuses a few fields without typing, so the form is touched and given up.
async function touchForm(page) {
    const fields = await page.locator(FIELDS).all();

    for (const field of fields.slice(0, 1 + Math.floor(Math.random() * 3))) {
        await field.scrollIntoViewIfNeeded().catch(() => {});
        await field.focus().catch(() => {});
        await pause(400, 1200);
    }
}

// Three quick presses on a heading or image: a rage click, and a dead one.
async function hammer(page) {
    const target = page.locator('h1, h2, img').first();

    if ((await target.count()) === 0) return;

    await target.scrollIntoViewIfNeeded().catch(() => {});

    const box = await target.boundingBox();

    if (!box) return;

    for (let press = 0; press < 3; press++) {
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await pause(60, 120);
    }

    await pause(1200, 1600);
}
