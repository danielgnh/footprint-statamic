<script setup>
import { CardPanel, Skeleton, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import { number } from '../../interactions/format.js';
import { useLazy } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
});

const root = ref(null);

const { data, failed } = useLazy(root, () => props.api('flows').then(({ data }) => data), () => props.params);

// A campaign tag says more than "Direct" about a visit that arrived without a referrer.
function source(row) {
    return [row.referrer_host, row.utm_source, row.utm_campaign].filter(Boolean).join(' · ') || 'Direct';
}

const lists = computed(() => {
    if (!data.value) return [];

    return [
        { heading: 'Came from', column: 'Previous page', unit: 'Views', empty: 'No view of this page in this range.', rows: data.value.previous.map((row) => ({ label: row.previous ?? 'Arrived on this page', count: row.views })) },
        { heading: 'Went next', column: 'Next page', unit: 'Views', empty: 'Nobody moved on inside the site.', rows: data.value.next.map((row) => ({ label: row.path, count: row.views })) },
        { heading: 'Visits that started here', column: 'Source', unit: 'Visits', empty: 'No visit started on this page.', rows: data.value.sources.map((row) => ({ label: source(row), count: row.visits })) },
    ];
});
</script>

<template>
    <div ref="root">
        <CardPanel heading="Flows" subheading="Where views of this page came from inside the site, where they went next, and what brought the visits that started here">
            <p v-if="failed" class="text-sm text-red-500">The flows could not be loaded.</p>
            <Skeleton v-else-if="!data" class="h-32" />
            <div v-else class="grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-6">
                <section v-for="list in lists" :key="list.heading" class="min-w-0">
                    <div class="mb-2 text-sm font-medium">{{ list.heading }}</div>
                    <p v-if="list.rows.length === 0" class="text-sm text-gray-500">{{ list.empty }}</p>
                    <Table v-else>
                        <TableColumns>
                            <TableColumn>{{ list.column }}</TableColumn>
                            <TableColumn class="w-16 text-right">{{ list.unit }}</TableColumn>
                        </TableColumns>
                        <TableRows>
                            <TableRow v-for="(row, index) in list.rows" :key="index">
                                <TableCell class="max-w-0">
                                    <div class="truncate" :title="row.label">{{ row.label }}</div>
                                </TableCell>
                                <TableCell class="text-right font-medium tabular-nums">{{ number(row.count) }}</TableCell>
                            </TableRow>
                        </TableRows>
                    </Table>
                </section>
            </div>
        </CardPanel>
    </div>
</template>
