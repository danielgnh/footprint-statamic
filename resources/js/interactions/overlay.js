import { pinned } from './sections.js';

const RADIUS = 28;
const LABEL = 26;

export function clearOverlay(doc) {
    doc.querySelector('[data-footprint-overlay]')?.remove();
}

// Draws into one root in document coordinates, so the layers scroll with the
// page. The root is data-footprint-ignore, so nothing about it is recorded.
export function drawOverlay(doc, sections, { rows, points, layers }) {
    clearOverlay(doc);

    const win = doc.defaultView;
    const root = doc.createElement('div');
    root.setAttribute('data-footprint-overlay', '');
    root.setAttribute('data-footprint-ignore', '');
    Object.assign(root.style, { position: 'absolute', left: '0', top: '0', width: '0', height: '0', pointerEvents: 'none', zIndex: '2147483647' });
    doc.documentElement.appendChild(root);

    const reached = new Map(rows.map((row) => [row.section, row]));
    const names = new Set([...reached.keys(), ...points.map((point) => point.section)]);
    const clicks = points.filter((point) => point.type === 'click');
    const densest = densestSpot(sections, clicks);
    const labels = [];

    for (const name of names) {
        const element = sections.get(name);

        if (!element || pinned(element)) continue;

        const box = element.getBoundingClientRect();

        if (box.width === 0 || box.height === 0) continue;

        const frame = doc.createElement('div');
        Object.assign(frame.style, {
            position: 'absolute',
            left: `${box.left + win.scrollX}px`,
            top: `${box.top + win.scrollY}px`,
            width: `${box.width}px`,
            height: `${box.height}px`,
        });
        root.appendChild(frame);

        const row = reached.get(name);

        if (layers.attention && row) attention(doc, frame, row, freeTop(labels, box.top + win.scrollY));
        if (layers.clicks) heat(doc, frame, box, clicks.filter((point) => point.section === name), densest);
        if (layers.frustration) markers(doc, frame, box, points.filter((point) => point.section === name && point.type !== 'click'));
    }
}

// Sections that start at the same height would stack their labels.
function freeTop(labels, top) {
    let offset = 0;

    while (labels.some((taken) => Math.abs(taken - (top + offset)) < LABEL)) offset += LABEL;

    labels.push(top + offset);

    return offset;
}

function attention(doc, frame, row, offset) {
    frame.style.background = `rgba(37, 99, 235, ${0.08 + 0.32 * (1 - row.reach)})`;
    frame.style.outline = '1px solid rgba(37, 99, 235, 0.5)';

    const label = doc.createElement('div');
    label.textContent = `${row.section}: ${Math.round(row.reach * 100)}% reached · ${Math.round(row.median_ms / 1000)} s`;
    Object.assign(label.style, {
        position: 'absolute',
        left: '8px',
        top: `${8 + offset}px`,
        padding: '2px 8px',
        borderRadius: '6px',
        font: '600 12px/1.5 system-ui, sans-serif',
        color: '#fff',
        background: 'rgba(15, 23, 42, 0.85)',
        whiteSpace: 'nowrap',
    });
    frame.appendChild(label);
}

// Stacked blobs make density alpha. The densest spot on the page maps to the
// hot end of the ramp, so a small sample still shows where it clusters.
function densestSpot(sections, clicks) {
    let densest = 1;
    const cells = new Map();

    for (const point of clicks) {
        const box = sections.get(point.section)?.getBoundingClientRect();

        if (!box) continue;

        const key = `${point.section}:${Math.round((point.x * box.width) / RADIUS)}:${Math.round((point.y * box.height) / RADIUS)}`;
        const count = (cells.get(key) ?? 0) + 1;
        cells.set(key, count);
        densest = Math.max(densest, count);
    }

    return densest;
}

function heat(doc, frame, box, points, densest) {
    if (points.length === 0) return;

    const ratio = doc.defaultView.devicePixelRatio || 1;
    const canvas = doc.createElement('canvas');
    canvas.width = Math.round(box.width * ratio);
    canvas.height = Math.round(box.height * ratio);
    Object.assign(canvas.style, { position: 'absolute', inset: '0', width: '100%', height: '100%' });

    const context = canvas.getContext('2d');

    if (!context) return;

    context.scale(ratio, ratio);

    const strength = Math.min(1, 0.9 / densest);

    for (const point of points) {
        const x = point.x * box.width;
        const y = point.y * box.height;
        const gradient = context.createRadialGradient(x, y, 0, x, y, RADIUS);
        gradient.addColorStop(0, `rgba(0, 0, 0, ${strength})`);
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
        context.fillStyle = gradient;
        context.fillRect(x - RADIUS, y - RADIUS, RADIUS * 2, RADIUS * 2);
    }

    const image = context.getImageData(0, 0, canvas.width, canvas.height);

    for (let i = 3; i < image.data.length; i += 4) {
        const density = image.data[i] / 255;

        if (density === 0) continue;

        const [r, g, b] = ramp(density);
        image.data[i - 3] = r;
        image.data[i - 2] = g;
        image.data[i - 1] = b;
        image.data[i] = Math.min(210, 60 + density * 300);
    }

    context.putImageData(image, 0, 0);
    frame.appendChild(canvas);
}

// Low density cool, high density hot.
function ramp(t) {
    const stops = [
        [59, 130, 246],
        [16, 185, 129],
        [250, 204, 21],
        [239, 68, 68],
    ];
    const scaled = Math.min(0.999, t) * (stops.length - 1);
    const [a, b] = [stops[Math.floor(scaled)], stops[Math.floor(scaled) + 1]];
    const f = scaled - Math.floor(scaled);

    return a.map((value, index) => Math.round(value + (b[index] - value) * f));
}

function markers(doc, frame, box, points) {
    for (const point of points) {
        const marker = doc.createElement('div');
        marker.title = `${point.type === 'rage' ? 'Rage click' : 'Dead click'}: ${point.target}`;
        Object.assign(marker.style, {
            position: 'absolute',
            left: `${point.x * box.width - 7}px`,
            top: `${point.y * box.height - 7}px`,
            width: '14px',
            height: '14px',
            borderRadius: point.type === 'rage' ? '50%' : '2px',
            border: `2px solid ${point.type === 'rage' ? '#dc2626' : '#6b7280'}`,
            background: 'rgba(255, 255, 255, 0.6)',
        });
        frame.appendChild(marker);
    }
}
