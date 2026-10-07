<?php

use Danielgnh\FootprintStatamic\Page;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

beforeEach(fn () => pages());

afterEach(fn () => Page::urlUsing(null));

it('joins an entry\'s edit page in the control panel and nowhere else', function () {
    expect(Entry::find('gravel')->blueprint()->hasField('footprint_interactions'))->toBeFalse();

    inControlPanel();

    $blueprint = Entry::find('gravel')->blueprint();

    expect($blueprint->hasField('footprint_interactions'))->toBeTrue()
        ->and($blueprint->field('footprint_interactions')->visibility())->toBe('computed')
        ->and($blueprint->tabs()->has('interactions'))->toBeTrue();

    // The blueprint builder is a request of its own, and asks without an entry.
    Blink::flush();

    expect(Collection::find('pages')->entryBlueprint()->hasField('footprint_interactions'))->toBeFalse();
});

it('only joins collections whose entries have a page, unless told which', function () {
    inControlPanel();

    expect(Entry::find('faq')->blueprint()->hasField('footprint_interactions'))->toBeFalse();

    config(['footprint-statamic.collections' => ['faqs']]);
    Blink::flush();

    expect(Entry::find('faq')->blueprint()->hasField('footprint_interactions'))->toBeTrue()
        ->and(Entry::find('gravel')->blueprint()->hasField('footprint_interactions'))->toBeFalse();
});

it('saves no value for the tab', function () {
    inControlPanel();

    $values = Entry::find('gravel')->blueprint()->fields()
        ->addValues(['footprint_interactions' => 'anything'])
        ->process()
        ->values();

    expect($values->has('footprint_interactions'))->toBeFalse();
});

it('hands the tab the entry\'s site-relative URL, so the frame stays on the control panel\'s origin', function () {
    inControlPanel();
    $this->actingAs(editor());

    expect(preload(Entry::find('gravel')))->toMatchArray([
        'available' => true,
        'url' => '/gravel',
        'path' => '/gravel',
        'framed' => true,
        'published' => true,
    ])
        ->and(data_get(preload(Entry::find('gravel')), 'endpoints.part'))->toEndWith('/cp/footprint/entries/gravel/__part__')
        ->and(data_get(preload(Entry::find('gravel')), 'endpoints.visit'))->toEndWith('/cp/footprint/visits/__visit__');
});

it('shows a localisation its own language\'s page', function () {
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_GB'],
        'pt' => ['name' => 'Português', 'url' => '/pt/', 'locale' => 'pt_PT'],
    ]);
    Collection::find('pages')->sites(['en', 'pt'])->routes(['en' => '{slug}', 'pt' => '{slug}'])->save();
    Entry::find('gravel')->makeLocalization('pt')->id('gravel-pt')->slug('cascalho')->save();

    inControlPanel();
    $this->actingAs(editor());

    expect(preload(Entry::find('gravel-pt')))->toMatchArray(['url' => '/pt/cascalho', 'path' => '/pt/cascalho']);
});

it('keeps sites on their own domains apart, and says their frame cannot be drawn over', function () {
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'en' => ['name' => 'English', 'url' => 'https://site.test/', 'locale' => 'en_GB'],
        'pt' => ['name' => 'Português', 'url' => 'https://site.pt/', 'locale' => 'pt_PT'],
    ]);
    Collection::find('pages')->sites(['en', 'pt'])->routes(['en' => '{slug}', 'pt' => '{slug}'])->save();
    Entry::find('gravel')->makeLocalization('pt')->id('gravel-pt')->slug('cascalho')->save();

    expect(Page::of(Entry::find('gravel-pt')))
        ->query()->toBe('//site.pt/cascalho')
        ->frame()->toBe('//site.pt/cascalho');

    inControlPanel();
    $this->actingAs(editor());

    expect(preload(Entry::find('gravel-pt')))->toMatchArray(['framed' => false]);
});

it('maps entries to the URL the front end serves them at', function () {
    Page::urlUsing(fn (EntryContract $entry) => "/bikes/{$entry->slug()}");

    inControlPanel();
    $this->actingAs(editor());

    expect(preload(Entry::find('gravel')))->toMatchArray(['url' => '/bikes/gravel', 'path' => '/bikes/gravel']);
});

it('offers nothing to an editor without the permission', function () {
    inControlPanel();
    $this->actingAs(editor(permitted: false));

    expect(preload(Entry::find('gravel')))->toBe(['available' => false]);
});
