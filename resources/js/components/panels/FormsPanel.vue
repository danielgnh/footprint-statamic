<script setup>
import { CardPanel, Heading, Skeleton, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { ref } from 'vue';
import { number } from '../../interactions/format.js';
import { useLazy } from '../../interactions/lazy.js';

const props = defineProps({
    api: { type: Function, required: true },
    params: { type: Object, required: true },
});

const root = ref(null);

const { data, failed } = useLazy(root, () => props.api('forms').then(({ data }) => data), () => props.params);
</script>

<template>
    <div ref="root">
        <CardPanel heading="Forms" subheading="The views that focused each field, and the views that ended on it without sending the form">
            <p v-if="failed" class="text-sm text-red-500">The forms could not be loaded.</p>
            <Skeleton v-else-if="!data" class="h-24" />
            <p v-else-if="data.length === 0" class="text-sm text-gray-500">No form on this page was touched in this range.</p>
            <div v-else class="space-y-6">
                <section v-for="form in data" :key="form.form ?? 'unnamed'">
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <Heading size="base" :text="form.form ?? 'Unnamed form'" />
                        <span class="text-sm text-gray-500">Touched in {{ number(form.views) }}, sent in {{ number(form.submitted) }} {{ form.submitted === 1 ? 'view' : 'views' }}</span>
                    </div>
                    <Table>
                        <TableColumns>
                            <TableColumn>Field</TableColumn>
                            <TableColumn class="w-28 text-right">Focused in</TableColumn>
                            <TableColumn class="w-28 text-right">Given up in</TableColumn>
                        </TableColumns>
                        <TableRows>
                            <TableRow v-for="field in form.fields" :key="field.field">
                                <TableCell class="font-medium">{{ field.field }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ number(field.views) }}</TableCell>
                                <TableCell class="text-right tabular-nums" :class="field.abandoned ? 'font-semibold text-red-500' : ''">{{ number(field.abandoned) }}</TableCell>
                            </TableRow>
                        </TableRows>
                    </Table>
                </section>
            </div>
        </CardPanel>
    </div>
</template>
