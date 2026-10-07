<script setup>
import { Button, ToggleGroup, ToggleItem } from '@statamic/cms/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { duration } from '../interactions/format.js';
import { clearPlayer, finish, play } from '../interactions/player.js';
import { exit, timeline } from '../interactions/timeline.js';

const props = defineProps({
    visit: { type: Object, required: true },
    events: { type: Array, required: true },
    path: { type: String, required: true },
    context: { type: Object, default: null },
});

const emit = defineEmits(['close']);

// When the scrubber replays history in one go, only this much of it flashes.
const AFTERGLOW = 1200;

const steps = computed(() => timeline(props.events, props.path));
const length = computed(() => (steps.value.at(-1)?.at ?? 0) + 1000);
const next = computed(() => exit(props.events, props.path));

const clock = ref(0);
const playing = ref(false);
const speed = ref('1');

let sections = new Map();
let ran = 0;
let frame = 0;
let last = 0;

const summary = computed(() => {
    const { device, viewport, lang, referrer_host } = props.visit;
    const spent = Math.max(0, ...props.events.map((event) => event.t));

    return [device, `${viewport} px`, duration(spent), lang, referrer_host ? `from ${referrer_host}` : 'direct'].filter(Boolean).join(' · ');
});

function advance(instantBefore = -1) {
    while (ran < steps.value.length && steps.value[ran].at <= clock.value) {
        const step = steps.value[ran];
        play(props.context.doc, sections, step, { referrer: props.visit.referrer_host, instant: step.at < instantBefore });
        ran++;
    }
}

function tick(now) {
    clock.value = Math.min(length.value, clock.value + (now - last) * Number(speed.value));
    last = now;
    advance();

    if (clock.value >= length.value) {
        pause();
        finish(props.context.doc, next.value);

        return;
    }

    frame = requestAnimationFrame(tick);
}

function start() {
    if (!props.context) return;

    if (clock.value >= length.value) seek(0);

    playing.value = true;
    last = performance.now();
    frame = requestAnimationFrame(tick);
}

function pause() {
    playing.value = false;
    cancelAnimationFrame(frame);
}

function seek(to) {
    if (!props.context) return;

    clearPlayer(props.context.doc);
    sections = props.context.sections();
    ran = 0;
    clock.value = Number(to);
    last = performance.now();
    advance(clock.value - AFTERGLOW);
}

watch(
    () => props.context,
    (context) => {
        pause();

        if (context) {
            seek(0);
            start();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    pause();

    try {
        if (props.context) clearPlayer(props.context.doc);
    } catch {
        // The frame has already gone.
    }
});
</script>

<template>
    <div class="space-y-2 rounded-lg border border-amber-500/50 bg-amber-500/5 p-3">
        <div class="flex flex-wrap items-center gap-3">
            <Button size="sm" variant="primary" :disabled="!context" :text="playing ? 'Pause' : 'Play'" @click="playing ? pause() : start()" />
            <ToggleGroup v-model="speed" size="xs" required>
                <ToggleItem value="1" label="1×" />
                <ToggleItem value="2" label="2×" />
                <ToggleItem value="4" label="4×" />
            </ToggleGroup>
            <input
                type="range"
                min="0"
                :max="length"
                :value="clock"
                :disabled="!context"
                aria-label="Position in the visit"
                class="min-w-40 flex-1 accent-amber-600"
                @input="seek($event.target.value)"
            />
            <span class="text-xs text-gray-500 tabular-nums">{{ duration(clock) }} / {{ duration(length) }}</span>
            <Button size="sm" variant="ghost" text="Close" @click="emit('close')" />
        </div>
        <p class="text-xs font-semibold text-amber-500">Reconstructed from recorded events, not a screen recording.</p>
        <p class="text-xs text-gray-500">{{ summary }}. Pauses longer than 2.5 s are shortened.</p>
    </div>
</template>
