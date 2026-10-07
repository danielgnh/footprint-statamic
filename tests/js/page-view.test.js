import { sectionName, sectionOf } from '@footprint/describe';
import { afterEach, describe, expect, it } from 'vitest';
import { clearOverlay, drawOverlay } from '../../resources/js/interactions/overlay.js';
import { sectionsIn } from '../../resources/js/interactions/sections.js';
import { exit, timeline } from '../../resources/js/interactions/timeline.js';

function html(markup) {
    document.body.innerHTML = markup;
}

function box(selector, rect) {
    document.querySelector(selector).getBoundingClientRect = () => ({ left: 0, top: 0, width: 400, height: 200, ...rect });
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('finding sections in the frame', () => {
    it('names them as the tracker does', () => {
        html(`
            <header id="top"><button id="a">Menu</button></header>
            <main>
                <div data-footprint-section="hero"><button id="b">Go</button></div>
                <section id="faq"><button id="c">Q</button></section>
                <section id="wrap"><div data-footprint-section="inner"><button id="d">In</button></div></section>
                <div data-footprint-section="account" data-footprint-ignore><button>Me</button></div>
            </main>
            <footer><button id="e">Legal</button></footer>
            <dialog aria-label="Geometry"><button id="f">Close</button></dialog>
        `);

        const found = sectionsIn(document);

        for (const id of ['#a', '#b', '#c', '#d', '#e', '#f']) {
            const section = sectionOf(document.querySelector(id));
            expect(found.get(sectionName(section))).toBe(section);
        }

        expect([...found.keys()]).toEqual(['top', 'hero', 'faq', 'inner', 'footer', 'Geometry']);
    });
});

describe('drawing the layers', () => {
    it('draws attention and frustration inside the page, ignored by the tracker', () => {
        html(`<div data-footprint-section="hero">Hero</div><div data-footprint-section="gone-from-page"></div>`);
        box('[data-footprint-section="hero"]', { top: 100 });

        drawOverlay(document, sectionsIn(document), {
            rows: [
                { section: 'hero', reach: 0.8, median_ms: 12_000 },
                { section: 'removed', reach: 0.4, median_ms: 3_000 },
            ],
            points: [
                { section: 'hero', type: 'rage', target: 'Pay', x: 0.5, y: 0.5 },
                { section: 'hero', type: 'dead', target: 'img', x: 0.25, y: 0.75 },
            ],
            layers: { attention: true, clicks: false, frustration: true },
        });

        const root = document.querySelector('[data-footprint-overlay]');

        expect(root.hasAttribute('data-footprint-ignore')).toBe(true);
        expect(root.children).toHaveLength(1);
        expect(root.textContent).toContain('hero: 80% reached · 12 s');
        expect(root.querySelectorAll('[title]')).toHaveLength(2);
        expect(root.querySelector('[title^="Rage"]').style.left).toBe('193px');

        clearOverlay(document);
        expect(document.querySelector('[data-footprint-overlay]')).toBeNull();
    });

    it('leaves fixed and sticky sections off the map', () => {
        html(`
            <header data-footprint-section="header" style="position: fixed">Menu</header>
            <div data-footprint-section="hero">Hero</div>
            <footer data-footprint-section="footer" style="position: sticky">Legal</footer>
        `);
        box('[data-footprint-section="hero"]', {});

        drawOverlay(document, sectionsIn(document), {
            rows: [{ section: 'hero', reach: 1, median_ms: 1_000 }],
            points: [
                { section: 'header', type: 'rage', target: 'Menu', x: 0.1, y: 0.5 },
                { section: 'footer', type: 'dead', target: 'Legal', x: 0.1, y: 0.5 },
            ],
            layers: { attention: true, clicks: false, frustration: true },
        });

        expect(document.querySelector('[data-footprint-overlay]').children).toHaveLength(1);
    });

    it('moves a label down when another section starts at the same height', () => {
        html(`<div data-footprint-section="hero">Hero</div><div data-footprint-section="intro">Intro</div>`);
        box('[data-footprint-section="hero"]', {});
        box('[data-footprint-section="intro"]', { top: 10 });

        drawOverlay(document, sectionsIn(document), {
            rows: [
                { section: 'hero', reach: 1, median_ms: 1_000 },
                { section: 'intro', reach: 0.5, median_ms: 1_000 },
            ],
            points: [],
            layers: { attention: true },
        });

        const labels = [...document.querySelectorAll('[data-footprint-overlay] > div > div')].map((label) => label.style.top);

        expect(labels).toEqual(['8px', '34px']);
    });
});

describe('replaying a visit', () => {
    it('builds a timeline for one page, capping long pauses', () => {
        const events = [
            { type: 'view', view: 1, t: 0, path: '/spa' },
            { type: 'view', view: 2, t: 1_000, path: '/bikes' },
            { type: 'section', view: 2, t: 1_200, path: '/bikes' },
            { type: 'click', view: 2, t: 31_200, path: '/bikes' },
            { type: 'view', view: 3, t: 32_000, path: '/quote' },
        ];

        expect(timeline(events, '/bikes').map((step) => [step.event.type, step.at])).toEqual([
            ['view', 0],
            ['section', 200],
            ['click', 2_700],
        ]);
        expect(exit(events, '/bikes')).toBe('/quote');
        expect(exit(events, '/quote')).toBeNull();
    });
});
