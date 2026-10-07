<script setup>
import { Fieldtype } from '@statamic/cms';
import { Alert, Button, Card, Description, Skeleton, ToggleGroup, ToggleItem } from '@statamic/cms/ui';
import { computed, getCurrentInstance, nextTick, ref, shallowRef, watch } from 'vue';
import { day, DEVICES, duration, number, share, SMALL_SAMPLE } from '../interactions/format.js';
import { useLazy } from '../interactions/lazy.js';
import ClicksPanel from './panels/ClicksPanel.vue';
import FlowsPanel from './panels/FlowsPanel.vue';
import FormsPanel from './panels/FormsPanel.vue';
import FrustrationPanel from './panels/FrustrationPanel.vue';
import PagePanel from './panels/PagePanel.vue';
import SectionsPanel from './panels/SectionsPanel.vue';
import VisitsPanel from './panels/VisitsPanel.vue';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const axios = getCurrentInstance().appContext.config.globalProperties.$axios;

const meta = computed(() => props.meta ?? {});
const root = ref(null);
const stage = ref(null);
const days = ref('30');
const device = ref('desktop');
const since = ref(null);
const found = ref(null);
const highlight = ref(null);
const playback = shallowRef(null);

const chosen = computed(() => (device.value === 'all' ? null : device.value));
const params = computed(() => ({ days: days.value, device: chosen.value ?? undefined, since: since.value ?? undefined }));

function api(part, page) {
    return axios.get(meta.value.endpoints.part.replace('__part__', part), { params: { ...params.value, page } }).then(({ data }) => data);
}

// The tab may mount hidden: nothing is fetched until it is shown.
const { data: figures, failed } = useLazy(root, () => (meta.value.available ? api('figures').then(({ data }) => data) : Promise.resolve(null)), params);

const views = computed(() => figures.value?.views ?? 0);
const small = computed(() => figures.value !== null && views.value < SMALL_SAMPLE);

const cards = computed(() => {
    const f = figures.value;

    if (!f) return [];

    return [
        { label: 'Views', value: number(f.views) },
        { label: 'Visits', value: number(f.visits) },
        { label: 'Median time on page', value: duration(f.median_time_ms) },
        { label: 'Scrolled past half', value: share(f.scrolled_half, f.views) },
        { label: 'Scrolled to the end', value: share(f.scrolled_all, f.views) },
        { label: 'Views with rage clicks', value: share(f.rage, f.views) },
        { label: 'Views with dead clicks', value: share(f.dead, f.views) },
        { label: 'Views that gave up a form', value: share(f.abandons, f.views) },
    ];
});

const edited = computed(() => {
    if (!meta.value.edited) return false;

    const start = new Date();
    start.setDate(start.getDate() - Number(days.value));

    return new Date(meta.value.edited) > start;
});

async function watchVisit(visit) {
    const { data } = await axios.get(meta.value.endpoints.visit.replace('__visit__', visit.id));
    playback.value = data.data;
    await nextTick();
    stage.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

watch(params, () => {
    found.value = null;
    playback.value = null;
});
</script>

<template>
    <div ref="root" class="space-y-6">
        <Description v-if="!meta.available" text="Interactions show for entries with a page on the site." />

        <template v-else>
            <div class="flex flex-wrap items-center gap-3">
                <ToggleGroup v-model="days" size="sm" required>
                    <ToggleItem value="7" label="7 days" />
                    <ToggleItem value="30" label="30 days" />
                    <ToggleItem value="90" label="90 days" />
                </ToggleGroup>
                <ToggleGroup v-model="device" size="sm" required>
                    <ToggleItem value="all" label="All devices" />
                    <ToggleItem v-for="(item, key) in DEVICES" :key="key" :value="key" :label="item.label" />
                </ToggleGroup>
            </div>

            <Alert v-if="!meta.published" variant="warning" text="This entry is not published, so the page has no visitors and the frame may not load." />

            <Alert v-if="edited" variant="default">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span v-if="since">Counting from the last edit on {{ day(meta.edited) }}.</span>
                    <span v-else>This page was edited on {{ day(meta.edited) }}, inside the range. Earlier data describes the page before that edit.</span>
                    <Button size="sm" :text="since ? 'Count the whole range' : 'Count from the edit'" @click="since = since ? null : meta.edited" />
                </div>
            </Alert>

            <Alert v-if="failed" variant="error" text="The interactions could not be loaded." />

            <Alert v-if="small" variant="tip" :text="`Small sample: ${number(views)} views. Shares are shown as counts, and no pattern here is a finding yet.`" />

            <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-3">
                <template v-if="figures">
                    <Card v-for="card in cards" :key="card.label">
                        <div class="text-xs text-gray-500">{{ card.label }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums">{{ card.value }}</div>
                    </Card>
                </template>
                <template v-else>
                    <Skeleton v-for="index in 8" :key="index" class="h-20" />
                </template>
            </div>

            <SectionsPanel :api="api" :params="params" :views="views" :found="found" @highlight="highlight = $event" />

            <PagePanel
                ref="stage"
                :api="api"
                :params="params"
                :url="meta.url"
                :path="meta.path"
                :framed="meta.framed"
                :device="chosen"
                :views="views"
                :playback="playback"
                :highlight="highlight"
                @found="found = $event"
                @close="playback = null"
            />

            <ClicksPanel :api="api" :params="params" :views="views" />
            <FrustrationPanel :api="api" :params="params" :views="views" />
            <FormsPanel :api="api" :params="params" />
            <FlowsPanel :api="api" :params="params" />
            <VisitsPanel :api="api" :params="params" :playing="playback?.visit.id ?? null" @watch="watchVisit" />
        </template>
    </div>
</template>
