<script setup>
import { Button, CardPanel, Skeleton, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { ref } from 'vue';
import { day, duration } from '../../interactions/format.js';
import { usePaged } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
    playing: { type: String, default: null },
});

const emit = defineEmits(['watch']);

const root = ref(null);
const sentinel = ref(null);

const { rows, more, loading, failed, started, next } = usePaged(root, sentinel, (page) => props.api('visits', page), () => props.params);

function length(visit) {
    return duration(visit.length_ms);
}
</script>

<template>
    <div ref="root">
        <CardPanel heading="Visits" subheading="Recent visits to this page, newest first. Watch one to see its recorded events replayed over the page.">
            <p v-if="failed && rows.length === 0" class="text-sm text-red-500">The visits could not be loaded.</p>
            <div v-else-if="!started" class="space-y-2">
                <Skeleton v-for="index in 5" :key="index" class="h-8" />
            </div>
            <p v-else-if="rows.length === 0" class="text-sm text-gray-500">No visits in this range.</p>
            <Table v-else>
                <TableColumns>
                    <TableColumn>Device</TableColumn>
                    <TableColumn class="text-right">Width</TableColumn>
                    <TableColumn class="text-right">Length</TableColumn>
                    <TableColumn>Language</TableColumn>
                    <TableColumn>From</TableColumn>
                    <TableColumn>Day</TableColumn>
                    <TableColumn class="w-24"><span class="sr-only">Playback</span></TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="visit in rows" :key="visit.id" :class="visit.id === playing ? 'bg-amber-500/10' : ''">
                        <TableCell class="font-medium capitalize">{{ visit.device }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ visit.viewport }} px</TableCell>
                        <TableCell class="text-right tabular-nums">{{ length(visit) }}</TableCell>
                        <TableCell>{{ visit.lang ?? '—' }}</TableCell>
                        <TableCell class="max-w-0">
                            <div class="truncate">{{ visit.referrer_host ?? (visit.utm_source ? `${visit.utm_source} campaign` : 'Direct') }}</div>
                        </TableCell>
                        <TableCell class="whitespace-nowrap">{{ day(visit.started_at) }}</TableCell>
                        <TableCell class="text-right">
                            <Button size="xs" :variant="visit.id === playing ? 'primary' : 'default'" text="Watch" @click="emit('watch', visit)" />
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>

            <div ref="sentinel" class="h-px" />
            <div v-if="started && (more || loading)" class="mt-3 flex justify-center">
                <Button size="sm" variant="ghost" :loading="loading" text="Load more" @click="next" />
            </div>
        </CardPanel>
    </div>
</template>
