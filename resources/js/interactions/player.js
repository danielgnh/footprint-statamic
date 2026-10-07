// Draws one step of a visit's playback inside the frame's document. Everything
// sits in one root marked data-footprint-ignore, in document coordinates, so it
// scrolls with the page and could never be recorded.

const FONT = '600 12px/1.5 system-ui, sans-serif';

export function clearPlayer(doc) {
    doc.querySelector('[data-footprint-player]')?.remove();
}

function layer(doc) {
    let root = doc.querySelector('[data-footprint-player]');

    if (!root) {
        root = doc.createElement('div');
        root.setAttribute('data-footprint-player', '');
        root.setAttribute('data-footprint-ignore', '');
        Object.assign(root.style, { position: 'absolute', left: '0', top: '0', width: '0', height: '0', pointerEvents: 'none', zIndex: '2147483647' });
        doc.documentElement.appendChild(root);
    }

    return root;
}

function place(doc, element, x, y, style = {}) {
    Object.assign(element.style, { position: 'absolute', left: `${x}px`, top: `${y}px`, ...style });
    layer(doc).appendChild(element);

    return element;
}

function fade(element, duration) {
    element.animate([{ opacity: 1 }, { opacity: 1, offset: 0.7 }, { opacity: 0 }], { duration, fill: 'forwards' }).finished.then(() => element.remove());
}

function box(element) {
    const win = element.ownerDocument.defaultView;
    const rect = element.getBoundingClientRect();

    return { left: rect.left + win.scrollX, top: rect.top + win.scrollY, width: rect.width, height: rect.height };
}

function at(sections, event) {
    const element = sections.get(event.section);

    if (!element || event.x === null || event.y === null) return null;

    const frame = box(element);

    return { x: frame.left + event.x * frame.width, y: frame.top + event.y * frame.height };
}

function label(doc, text, x, y, colour) {
    const tag = doc.createElement('div');
    tag.textContent = text;

    return place(doc, tag, x, y, { padding: '2px 8px', borderRadius: '6px', font: FONT, color: '#fff', background: colour, whiteSpace: 'nowrap', maxWidth: '280px', overflow: 'hidden', textOverflow: 'ellipsis' });
}

// A card pinned to the top of the viewport, where the eye is.
function card(doc, text, duration = 2400) {
    const win = doc.defaultView;
    const tag = label(doc, text, win.scrollX + 16, win.scrollY + 16, 'rgba(15, 23, 42, 0.9)');
    Object.assign(tag.style, { padding: '6px 12px', fontSize: '13px' });
    fade(tag, duration);
}

function ring(doc, x, y, colour, delay = 0) {
    const circle = place(doc, doc.createElement('div'), x - 14, y - 14, { width: '28px', height: '28px', borderRadius: '50%', border: `3px solid ${colour}` });
    circle.animate([{ transform: 'scale(0.4)', opacity: 1 }, { transform: 'scale(2.2)', opacity: 0 }], { duration: 900, delay, easing: 'ease-out', fill: 'both' }).finished.then(() => circle.remove());
}

function field(doc, name) {
    const escaped = CSS.escape(name);
    const found = doc.querySelector(`[name="${escaped}"]`) ?? doc.querySelector(`[data-footprint="${escaped}"]`);

    if (found) return found;

    return [...doc.querySelectorAll('label')].find((element) => element.textContent.replace(/\s+/g, ' ').trim() === name)?.control ?? null;
}

function form(doc, name) {
    if (!name) return null;

    const escaped = CSS.escape(name);

    return doc.querySelector(`form[name="${escaped}"], form[data-footprint="${escaped}"], form#${escaped}, form[aria-label="${escaped}"]`);
}

function outline(doc, element, colour, text) {
    const frame = box(element);
    const ring = place(doc, doc.createElement('div'), frame.left - 4, frame.top - 4, {
        width: `${frame.width + 8}px`,
        height: `${frame.height + 8}px`,
        borderRadius: '8px',
        outline: `2px solid ${colour}`,
    });

    if (text) label(doc, text, frame.left, frame.top - 26, colour);

    return ring;
}

export function scrollTo(win, top, instant) {
    win.scrollTo({ top: Math.max(0, top), behavior: instant ? 'instant' : 'smooth' });
}

// Steps that only flash are skipped when the scrubber replays history in one go.
export function play(doc, sections, step, { referrer, instant = false }) {
    const win = doc.defaultView;
    const { event } = step;

    switch (event.type) {
        case 'view':
            scrollTo(win, 0, instant);
            if (!instant) card(doc, `Arrived from ${event.previous ?? referrer ?? 'direct'}`);
            break;

        case 'section':
            sections.get(event.section)?.scrollIntoView({ behavior: instant ? 'instant' : 'smooth', block: 'nearest' });
            break;

        case 'scroll':
            scrollTo(win, (event.value / 100) * doc.documentElement.scrollHeight - win.innerHeight, instant);
            break;

        case 'click': {
            const point = at(sections, event);

            if (instant || !point) break;

            ring(doc, point.x, point.y, '#2563eb');
            fade(label(doc, event.target ?? 'Click', point.x + 16, point.y - 10, 'rgba(37, 99, 235, 0.92)'), 1600);
            break;
        }

        case 'rage': {
            const point = at(sections, event);

            if (instant || !point) break;

            [0, 150, 300].forEach((delay) => ring(doc, point.x, point.y, '#dc2626', delay));
            fade(label(doc, `Rage: ${event.target ?? 'click'}`, point.x + 16, point.y - 10, 'rgba(220, 38, 38, 0.92)'), 1800);
            break;
        }

        case 'dead': {
            const point = at(sections, event);

            if (instant || !point) break;

            const cross = place(doc, doc.createElement('div'), point.x - 10, point.y - 14, { font: '700 24px/1 system-ui, sans-serif', color: '#6b7280' });
            cross.textContent = '×';
            fade(cross, 1400);
            break;
        }

        case 'field': {
            const element = event.target && field(doc, event.target);

            if (instant || !element) break;

            fade(outline(doc, element, '#2563eb'), 1600);
            break;
        }

        case 'submit': {
            const element = form(doc, event.form);

            if (element) {
                outline(doc, element, '#16a34a', '✓ Sent');
            } else if (!instant) {
                card(doc, `✓ Sent ${event.form ?? 'a form'}`);
            }
            break;
        }

        case 'abandon': {
            const element = event.target && field(doc, event.target);

            if (element) {
                outline(doc, element, '#dc2626', 'Left here');
            } else if (!instant) {
                card(doc, `Left ${event.form ?? 'a form'} at ${event.target ?? 'a field'}`);
            }
            break;
        }

        case 'custom':
            if (!instant) card(doc, event.target ?? 'Custom event', 1800);
            break;
    }
}

export function finish(doc, exit) {
    card(doc, exit ? `Went on to ${exit}` : 'Left the site', 60_000);
}
