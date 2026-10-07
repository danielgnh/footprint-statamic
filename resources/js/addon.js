import InteractionsFieldtype from './components/InteractionsFieldtype.vue';

Statamic.booting(() => {
    Statamic.component('footprint_interactions-fieldtype', InteractionsFieldtype);
});
