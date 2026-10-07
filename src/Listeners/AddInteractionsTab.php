<?php

namespace Danielgnh\FootprintStatamic\Listeners;

use Statamic\Contracts\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Statamic;

/**
 * The event also fires in the blueprint builder and on the create page
 * (without an entry), and on the front end while it renders. The field joins
 * only an existing entry's edit page, so a saved blueprint never writes it.
 */
class AddInteractionsTab
{
    public function handle(EntryBlueprintFound $event): void
    {
        if (! $event->entry instanceof Entry || ! Statamic::isCpRoute() || ! $this->wants($event->entry)) {
            return;
        }

        $event->blueprint->ensureFieldInTab('footprint_interactions', [
            'type' => 'footprint_interactions',
            'visibility' => 'computed',
            'listable' => false,
            'hide_display' => true,
        ], 'interactions');
    }

    private function wants(Entry $entry): bool
    {
        $collections = config('footprint-statamic.collections');

        if ($collections === null) {
            return filled($entry->collection()->route($entry->locale()));
        }

        return in_array($entry->collectionHandle(), (array) $collections, true);
    }
}
