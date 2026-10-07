<script setup>
import { Button } from '@statamic/cms/ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { clearOverlay, drawOverlay } from '../interactions/overlay.js';
import { clearPlayer } from '../interactions/player.js';
import { sectionsIn, waitForSections } from '../interactions/sections.js';

const props = defineProps({
    url: { type: String, required: true },
    width: { type: Number, required: true },
    height: { type: Number, required: true },
    rows: { type: Array, default: () => [] },
    points: { type: Array, default: () => [] },
    layers: { type: Object, default: () => ({}) },
    highlight: { type: String, default: null },
});

const emit = defineEmits(['ready', 'found']);

const box = ref(null);
const iframe = ref(null);
const available = ref(0);
const status = ref('loading');

const scale = computed(() => (available.value > 0 ? Math.min(1, available.value / props.width) : 1));
const expected = new URL(props.url, window.location.origin).pathname;

let doc = null;
let sections = new Map();
let frame = 0;
let poll = 0;
let outer = null;
let inner = null;

function draw() {
    if (!doc || status.value !== 'ready') return;

    sections = sectionsIn(doc);
    drawOverlay(doc, sections, { rows: props.rows, points: props.points, layers: props.layers });
    outlineHighlight();
}

function schedule() {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(draw);
}

function outlineHighlight() {
    doc.querySelector('[data-footprint-highlight]')?.remove();

    const element = props.highlight && sections.get(props.highlight);

    if (!element) return;

    const win = doc.defaultView;
    const rect = element.getBoundingClientRect();
    const outline = doc.createElement('div');
    outline.setAttribute('data-footprint-highlight', '');
    outline.setAttribute('data-footprint-ignore', '');
    Object.assign(outline.style, {
        position: 'absolute',
        left: `${rect.left + win.scrollX}px`,
        top: `${rect.top + win.scrollY}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
        outline: '3px solid #f59e0b',
        outlineOffset: '-3px',
        pointerEvents: 'none',
        zIndex: '2147483647',
    });
    doc.documentElement.appendChild(outline);
}

async function loaded() {
    inner?.disconnect();

    doc = iframe.value.contentDocument;

    if (!doc) {
        status.value = 'blocked';
        return;
    }

    if (doc.defaultView.location.pathname !== expected) {
        status.value = 'away';
        return;
    }

    await waitForSections(doc);
    await doc.fonts?.ready;

    status.value = 'ready';
    draw();
    emit('found', [...sections.keys()]);
    emit('ready', { doc, sections: () => sectionsIn(doc) });

    inner = new doc.defaultView.ResizeObserver(schedule);
    inner.observe(doc.documentElement);
}

// An Inertia page changes its path without a load event, so the path is polled.
function watchPath() {
    try {
        const path = iframe.value?.contentWindow?.location.pathname;

        if (status.value === 'ready' && path && path !== expected) {
            clear();
            status.value = 'away';
        }
    } catch {
        status.value = 'blocked';
    }
}

function clear() {
    if (!doc) return;

    try {
        clearOverlay(doc);
        clearPlayer(doc);
        doc.querySelector('[data-footprint-highlight]')?.remove();
    } catch {
        // The frame has already gone.
    }
}

function back() {
    status.value = 'loading';
    iframe.value.src = props.url;
}

onMounted(() => {
    outer = new ResizeObserver(([entry]) => (available.value = entry.contentRect.width));
    outer.observe(box.value);
    poll = window.setInterval(watchPath, 500);
});

onBeforeUnmount(() => {
    clear();
    cancelAnimationFrame(frame);
    window.clearInterval(poll);
    outer?.disconnect();
    inner?.disconnect();
});

watch(() => [props.rows, props.points, props.highlight, ...Object.values(props.layers)], schedule);
</script>

<template>
    <div ref="box" class="w-full">
        <div class="relative overflow-hidden rounded-lg border border-gray-500/25 bg-gray-500/5" :style="{ height: `${height * scale}px` }">
            <iframe
                ref="iframe"
                :src="url"
                title="The live page"
                class="absolute top-0 left-0 border-0 bg-white"
                :style="{ width: `${width}px`, height: `${height}px`, transform: `scale(${scale})`, transformOrigin: '0 0' }"
                @load="loaded"
            />

            <div v-if="status !== 'ready'" class="absolute inset-0 flex items-center justify-center bg-white/85 p-6 text-center dark:bg-gray-900/85">
                <p v-if="status === 'loading'" class="text-sm text-gray-500">Loading the page…</p>
                <div v-else-if="status === 'away'" class="space-y-3">
                    <p class="text-sm">The frame left this page, so the layers are hidden.</p>
                    <Button size="sm" text="Back to the page" @click="back" />
                </div>
                <p v-else class="max-w-md text-sm">
                    The page could not be read inside the control panel. A server header such as X-Frame-Options or a frame-ancestors policy may be blocking it.
                </p>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Today's page at {{ width }} px wide{{ scale < 1 ? `, shown at ${Math.round(scale * 100)}%` : '' }}. The data may be older than its current layout.
        </p>
    </div>
</template>
