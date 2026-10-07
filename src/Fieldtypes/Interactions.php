<?php

namespace Danielgnh\FootprintStatamic\Fieldtypes;

use Danielgnh\FootprintStatamic\Page;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\User;
use Statamic\Fields\Fieldtype;

/**
 * The Interactions tab: display-only, computed, so it never saves a value.
 */
class Interactions extends Fieldtype
{
    /** @var string */
    protected static $handle = 'footprint_interactions';

    /** @var bool */
    protected $selectable = false;

    /**
     * Runs again on every save, so it hands over URLs only. The tab fetches
     * the numbers itself.
     *
     * @return array<string, mixed>
     */
    public function preload(): array
    {
        $entry = $this->field()?->parent();

        if (! $entry instanceof Entry || ! User::current()?->can('view interactions')) {
            return ['available' => false];
        }

        $page = Page::of($entry);

        if (! $page instanceof Page) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'url' => $page->frame(),
            'path' => $page->path(),
            'framed' => $page->host === null || $page->host === request()->getHost(),
            'published' => $entry->published(),
            'edited' => $entry->lastModified()?->toIso8601String(),
            'endpoints' => [
                'part' => cp_route('footprint.interactions.show', ['entry' => $entry->id(), 'part' => '__part__']),
                'visit' => cp_route('footprint.visits.show', ['visit' => '__visit__']),
            ],
        ];
    }

    public function process(mixed $data): null
    {
        return null;
    }
}
