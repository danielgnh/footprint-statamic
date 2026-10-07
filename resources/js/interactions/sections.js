import { LANDMARK, SECTION, ignored, sectionName } from '@footprint/describe';

// The tracker's own rules for what a section is and what it is called.
export function sectionsIn(doc) {
    const marked = [...doc.querySelectorAll(SECTION)];
    const landmarks = [...doc.querySelectorAll(LANDMARK)].filter((element) => !element.closest(SECTION) && !element.querySelector(SECTION));
    const byName = new Map();

    for (const element of [...marked, ...landmarks].sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))) {
        const name = sectionName(element);

        if (!ignored(element) && !byName.has(name)) byName.set(name, element);
    }

    return byName;
}

// A fixed or sticky section sits wherever the frame happens to be scrolled,
// so the map leaves it out. Its clicks are still in the lists.
export function pinned(element) {
    return ['fixed', 'sticky'].includes(element.ownerDocument.defaultView.getComputedStyle(element).position);
}

// Pages that render on the client (Inertia, Livewire, Turbo) add their
// sections after the load event.
export async function waitForSections(doc, timeout = 4000) {
    const until = performance.now() + timeout;

    while (performance.now() < until && !doc.querySelector(SECTION)) {
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
}
