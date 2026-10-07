<script setup>
import { Badge, CardPanel, Skeleton } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import { duration, percent, share, SMALL_SAMPLE } from '../../interactions/format.js';
import { useLazy } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
    views: { type: Number, default: 0 },
    found: { type: Array, default: null },
});

const emit = defineEmits(['highlight']);

const root = ref(null);

const { data, failed } = useLazy(
    root,
    () => Promise.all([props.api('sections'), props.api('scroll')]).then(([sections, scroll]) => ({ sections: sections.data, scroll: scroll.data })),
    () => props.params,
);

const small = computed(() => props.views < SMALL_SAMPLE);
const rows = computed(() => (data.value?.sections ?? []).map((row) => ({ ...row, gone: props.found !== null && !props.found.includes(row.section) })));
</script>

<template>
    <div ref="root">
        <CardPanel heading="Sections" :subheading="small ? 'Views that reached each section, in page order, and the median time it stayed on screen' : 'Share of views that reached each section, in page order, and the median time it stayed on screen'">
            <p v-if="failed" class="text-sm text-red-500">The sections could not be loaded.</p>
            <div v-else-if="!data" class="space-y-2">
                <Skeleton v-for="index in 6" :key="index" class="h-6" />
            </div>
            <p v-else-if="rows.length === 0" class="text-sm text-gray-500">No section was reached in this range.</p>
            <template v-else>
                <ol class="space-y-0.5">
                    <li
                        v-for="row in rows"
                        :key="row.section"
                        tabindex="0"
                        class="grid grid-cols-[minmax(8rem,14rem)_minmax(0,1fr)_4rem_4.5rem] items-center gap-4 rounded-md px-2 py-2 outline-none hover:bg-gray-500/10 focus-visible:ring-2 focus-visible:ring-blue-500"
                        @mouseenter="emit('highlight', row.section)"
                        @mouseleave="emit('highlight', null)"
                        @focus="emit('highlight', row.section)"
                        @blur="emit('highlight', null)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate font-mono text-[13px]" :title="row.section">{{ row.section }}</span>
                            <Badge v-if="row.gone" size="sm" color="amber" text="Not on the page" />
                        </span>
                        <span class="h-2 overflow-hidden rounded-full bg-gray-500/15" aria-hidden="true">
                            <span class="block h-full rounded-full bg-blue-500" :style="{ width: percent(row.reach) }" />
                        </span>
                        <span class="text-right text-sm font-medium tabular-nums">{{ share({ views: row.views, share: row.reach }, views) }}</span>
                        <span class="text-right text-sm text-gray-500 tabular-nums">{{ duration(row.median_ms) }}</span>
                    </li>
                </ol>

                <div class="mt-5 border-t border-gray-500/15 pt-4">
                    <div class="mb-3 text-xs font-medium text-gray-500">How far down views scrolled</div>
                    <div class="grid grid-cols-10 gap-1.5">
                        <div v-for="step in data.scroll" :key="step.depth" class="text-center">
                            <div class="flex h-14 items-end overflow-hidden rounded bg-gray-500/15" aria-hidden="true">
                                <div class="w-full rounded-t bg-blue-500" :style="{ height: percent(step.share) }" />
                            </div>
                            <div class="mt-1.5 text-[11px] text-gray-500">{{ step.depth }}%</div>
                            <div class="text-xs font-medium tabular-nums">{{ share(step, views) }}</div>
                        </div>
                    </div>
                </div>
            </template>
        </CardPanel>
    </div>
</template>
