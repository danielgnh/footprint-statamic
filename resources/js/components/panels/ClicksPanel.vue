<script setup>
import { Badge, Button, CardPanel, Skeleton, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { ref } from 'vue';
import { number, percent, share } from '../../interactions/format.js';
import { usePaged } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
    views: { type: Number, default: 0 },
});

const root = ref(null);
const sentinel = ref(null);

const { rows, more, loading, failed, started, next } = usePaged(root, sentinel, (page) => props.api('clicks', page), () => props.params);

</script>

<template>
    <div ref="root">
        <CardPanel heading="Most clicked" subheading="Links, buttons and controls, by the views that clicked them at least once, so one visitor clicking again and again counts once.">
            <p v-if="failed && rows.length === 0" class="text-sm text-red-500">The clicks could not be loaded.</p>
            <div v-else-if="!started" class="space-y-2">
                <Skeleton v-for="index in 5" :key="index" class="h-8" />
            </div>
            <p v-else-if="rows.length === 0" class="text-sm text-gray-500">No clicks in this range.</p>
            <Table v-else>
                <TableColumns>
                    <TableColumn>Element</TableColumn>
                    <TableColumn class="w-44">Section</TableColumn>
                    <TableColumn class="w-20 text-right">Clicks</TableColumn>
                    <TableColumn class="w-40 text-right">Views</TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="(click, index) in rows" :key="index">
                        <TableCell class="max-w-0">
                            <div class="truncate font-medium" :title="click.target">{{ click.target ?? '(unnamed)' }}</div>
                            <div v-if="click.href" class="truncate text-xs text-gray-500" :title="click.href">{{ click.href }}</div>
                        </TableCell>
                        <TableCell class="whitespace-nowrap">
                            <Badge v-if="click.section" size="sm" :text="click.section" />
                            <span v-else class="text-gray-400">—</span>
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">{{ number(click.clicks) }}</TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-3">
                                <span class="block h-1.5 w-16 overflow-hidden rounded-full bg-gray-500/15" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-blue-500" :style="{ width: percent(click.share) }" />
                                </span>
                                <span class="w-10 text-right tabular-nums">{{ share(click, views) }}</span>
                            </div>
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
