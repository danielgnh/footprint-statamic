const LONGEST_PAUSE = 2500;

export function timeline(events, path) {
    const views = new Set(events.filter((event) => event.type === 'view' && event.path === path).map((event) => event.view));
    let clock = 0;
    let last = null;

    return events
        .filter((event) => views.has(event.view))
        .map((event) => {
            if (last !== null) clock += Math.min(Math.max(0, event.t - last), LONGEST_PAUSE);
            last = event.t;

            return { at: clock, event };
        });
}

// The view that came after this page's last view, for the closing card.
export function exit(events, path) {
    const last = Math.max(...events.filter((event) => event.type === 'view' && event.path === path).map((event) => event.view));

    return events.find((event) => event.type === 'view' && event.view === last + 1)?.path ?? null;
}
