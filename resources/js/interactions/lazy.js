import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

// Starts loading a little before the element scrolls into view.
const MARGIN = '300px';

function whenSeen(target, callback) {
    let observer = null;

    onMounted(() => {
        observer = new IntersectionObserver(([entry]) => entry.isIntersecting && callback(), { rootMargin: MARGIN });
        observer.observe(target.value);
    });

    onBeforeUnmount(() => observer?.disconnect());

    return () => {
        // Observing again reports the current state, so a sentinel still on screen asks for more.
        if (!observer || !target.value) return;
        observer.unobserve(target.value);
        observer.observe(target.value);
    };
}

// One part of the report, fetched the first time its panel is on screen and
// again whenever the filters change after that.
export function useLazy(target, fetch, params) {
    const data = shallowRef(null);
    const failed = ref(false);
    const seen = ref(false);
    let request = 0;

    async function load() {
        const mine = ++request;
        failed.value = false;

        try {
            const result = await fetch();
            if (mine === request) data.value = result;
        } catch {
            if (mine === request) failed.value = true;
        }
    }

    whenSeen(target, () => {
        if (seen.value) return;
        seen.value = true;
        load();
    });

    watch(params, () => {
        data.value = null;
        if (seen.value) load();
    });

    return { data, failed, seen, reload: load };
}

// A list fetched a page at a time: the first page when the panel is on screen,
// the next whenever the sentinel under the rows comes into view.
export function usePaged(target, sentinel, fetchPage, params) {
    const rows = shallowRef([]);
    const more = ref(false);
    const loading = ref(false);
    const failed = ref(false);
    const started = ref(false);
    let seen = false;
    let page = 0;
    let request = 0;

    async function next() {
        if (loading.value) return;

        const mine = request;
        loading.value = true;
        failed.value = false;

        try {
            const result = await fetchPage(page + 1);
            if (mine !== request) return;
            page++;
            rows.value = [...rows.value, ...result.data];
            more.value = result.next_page_url !== null;
            started.value = true;
        } catch {
            if (mine === request) failed.value = true;
        } finally {
            if (mine === request) loading.value = false;
        }

        if (mine === request && more.value) recheck();
    }

    whenSeen(target, () => {
        if (seen) return;
        seen = true;
        next();
    });
    const recheck = whenSeen(sentinel, () => started.value && more.value && next());

    watch(params, () => {
        request++;
        page = 0;
        rows.value = [];
        more.value = false;
        loading.value = false;
        started.value = false;
        if (seen) next();
    });

    return { rows, more, loading, failed, started, next };
}
