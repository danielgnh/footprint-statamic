<script setup>
import { CardPanel, Switch } from '@statamic/cms/ui';
import { computed, ref, shallowRef, watch } from 'vue';
import { DEVICES, number } from '../../interactions/format.js';
import { useLazy } from '../../interactions/lazy.js';
import PageFrame from '../PageFrame.vue';
import VisitPlayer from '../VisitPlayer.vue';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
    url: { type: String, required: true },
    path: { type: String, required: true },
    framed: { type: Boolean, default: true },
    device: { type: String, default: null },
    views: { type: Number, default: 0 },
    playback: { type: Object, default: null },
    highlight: { type: String, default: null },
});

const emit = defineEmits(['found', 'close']);

const root = ref(null);
const layers = ref({ attention: true, clicks: true, frustration: true });
const found = ref(null);
const context = shallowRef(null);

const { data, seen } = useLazy(
    root,
    async () => {
        if (!props.device) return { rows: [], points: [] };

        const [sections, points] = await Promise.all([props.api('sections'), props.api('points')]);

        return { rows: sections.data, points: points.data };
    },
    () => props.params,
);

const frame = computed(() => {
    if (props.playback) {
        const { viewport, device } = props.playback.visit;

        return { width: Math.min(1920, Math.max(320, viewport)), height: DEVICES[device]?.height ?? 900 };
    }

    return props.device ? DEVICES[props.device] : null;
});

const frameKey = computed(() => (props.playback ? `visit-${frame.value.width}` : `device-${props.device}`));
const rows = computed(() => (data.value?.rows ?? []).filter((row) => found.value === null || found.value.includes(row.section)));
const points = computed(() => data.value?.points ?? []);

const counts = computed(() => ({
    clicks: points.value.filter((point) => point.type === 'click').length,
    rage: points.value.filter((point) => point.type === 'rage').length,
    dead: points.value.filter((point) => point.type === 'dead').length,
}));

function onFound(names) {
    found.value = names;
    emit('found', names);
}

watch(
    () => [props.playback, props.device],
    () => {
        context.value = null;
        found.value = null;
    },
);
</script>

<template>
    <div ref="root" class="scroll-mt-16">
        <CardPanel heading="The page" :subheading="playback ? 'One visit replayed over today\'s page' : 'Today\'s page with the data drawn over it'">
            <p v-if="!framed" class="text-sm text-gray-500">
                This page is served on another domain than the control panel, so the map cannot be drawn over it. The lists carry every number.
            </p>

            <p v-else-if="!frame" class="text-sm text-gray-500">
                Click positions belong to one layout, so the map shows one device at a time. Pick a device above to see it.
            </p>

            <div v-else-if="seen" class="space-y-4">
                <VisitPlayer v-if="playback" :visit="playback.visit" :events="playback.events" :path="path" :context="context" @close="emit('close')" />

                <div v-else class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <label class="flex items-center gap-2"><Switch v-model="layers.attention" size="sm" /> Attention</label>
                    <label class="flex items-center gap-2"><Switch v-model="layers.clicks" size="sm" /> Clicks</label>
                    <label class="flex items-center gap-2"><Switch v-model="layers.frustration" size="sm" /> Frustration</label>
                </div>

                <PageFrame
                    :key="frameKey"
                    :url="url"
                    :width="frame.width"
                    :height="frame.height"
                    :rows="playback ? [] : rows"
                    :points="playback ? [] : points"
                    :layers="playback ? {} : layers"
                    :highlight="playback ? null : highlight"
                    @found="onFound"
                    @ready="context = $event"
                />

                <ul v-if="!playback" class="space-y-1 text-xs text-gray-500">
                    <li>Attention: each section is tinted darker the fewer views reached it, and labelled with its reach and median time on screen.</li>
                    <li>Clicks: how dense clicks are inside each section, from cool blue for a few to red for the most.</li>
                    <li>Frustration: a red circle is a rage click, a grey square a dead click.</li>
                    <li>Fixed and sticky sections, such as a sticky header, sit wherever the frame is scrolled, so they are left off the map. Their clicks are in the lists.</li>
                    <li v-if="data">
                        Drawn: {{ number(counts.clicks) }} clicks, {{ number(counts.rage) }} rage and {{ number(counts.dead) }} dead clicks from
                        {{ number(views) }} {{ DEVICES[device].label.toLowerCase() }} views, newest first, at most 5,000.
                    </li>
                </ul>
            </div>
        </CardPanel>
    </div>
</template>
