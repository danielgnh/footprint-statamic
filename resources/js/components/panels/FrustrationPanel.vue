<script setup>
import { Badge, Button, CardPanel, Skeleton, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { ref } from 'vue';
import { number, share } from '../../interactions/format.js';
import { usePaged } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
    views: { type: Number, default: 0 },
});

const root = ref(null);
const sentinel = ref(null);

const { rows, more, loading, failed, started, next } = usePaged(root, sentinel, (page) => props.api('frustration', page), () => props.params);
</script>

<template>
    <div ref="root">
        <CardPanel heading="Frustration" subheading="Rage clicks are three or more presses on one spot in quick succession. Dead clicks are clicks on something that answered nothing. Ranked by the views they happened in.">
            <p v-if="failed && rows.length === 0" class="text-sm text-red-500">The frustration could not be loaded.</p>
            <div v-else-if="!started" class="space-y-2">
                <Skeleton v-for="index in 4" :key="index" class="h-8" />
            </div>
            <p v-else-if="rows.length === 0" class="text-sm text-gray-500">No rage or dead clicks in this range.</p>
            <Table v-else>
                <TableColumns>
                    <TableColumn class="w-24">Kind</TableColumn>
                    <TableColumn>Element</TableColumn>
                    <TableColumn class="w-44">Section</TableColumn>
                    <TableColumn class="w-20 text-right">Presses</TableColumn>
                    <TableColumn class="w-20 text-right">Views</TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="(row, index) in rows" :key="index">
                        <TableCell class="whitespace-nowrap">
                            <Badge size="sm" :color="row.type === 'rage' ? 'red' : 'default'" :text="row.type === 'rage' ? 'Rage' : 'Dead'" />
                        </TableCell>
                        <TableCell class="max-w-0">
                            <div class="truncate font-medium" :title="row.target">{{ row.target ?? '(unnamed)' }}</div>
                        </TableCell>
                        <TableCell class="whitespace-nowrap">
                            <Badge v-if="row.section" size="sm" :text="row.section" />
                            <span v-else class="text-gray-400">—</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ number(row.count) }}</TableCell>
                        <TableCell class="text-right font-medium tabular-nums">{{ share(row, views) }}</TableCell>
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
