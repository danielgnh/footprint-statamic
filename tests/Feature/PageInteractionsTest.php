<?php

use Danielgnh\FootprintStatamic\PageInteractions;

beforeEach(fn () => recordGravelPage());

it('counts a page\'s views, time and scroll depth, and the views with frustration', function () {
    expect(interactions()->figures())->toBe([
        'views' => 3,
        'visits' => 3,
        'median_time_ms' => 20_000,
        'scrolled_half' => ['views' => 2, 'share' => 0.667],
        'scrolled_all' => ['views' => 1, 'share' => 0.333],
        'rage' => ['views' => 1, 'share' => 0.333],
        'dead' => ['views' => 1, 'share' => 0.333],
        'abandons' => ['views' => 1, 'share' => 0.333],
    ]);
});

it('orders sections down the page with their reach and the median of each view\'s summed dwell', function () {
    expect(interactions()->sections())->toBe([
        ['section' => 'billboard', 'position' => 1.0, 'views' => 3, 'reach' => 1.0, 'median_ms' => 6_000],
        ['section' => 'story', 'position' => 2.0, 'views' => 1, 'reach' => 0.333, 'median_ms' => 6_000],
    ]);
});

it('counts views at every scroll step', function () {
    $scroll = collect(interactions()->scroll())->keyBy('depth');

    expect($scroll->keys()->all())->toBe(range(10, 100, 10))
        ->and($scroll[50])->toBe(['depth' => 50, 'views' => 2, 'share' => 0.667])
        ->and($scroll[60])->toBe(['depth' => 60, 'views' => 1, 'share' => 0.333]);
});

it('ranks clicks and frustration by the views that had them, so one hammering visitor counts once', function () {
    expect(interactions()->clicks())->toBe([
        ['target' => 'Build yours', 'section' => 'billboard', 'href' => '/gravel/build', 'views' => 2, 'clicks' => 2, 'share' => 0.667],
    ])
        ->and(interactions()->frustration())->toBe([
            ['type' => 'rage', 'target' => 'Ride it first', 'section' => 'billboard', 'views' => 1, 'count' => 5, 'share' => 0.333],
            ['type' => 'dead', 'target' => 'Four paints', 'section' => 'paints', 'views' => 1, 'count' => 1, 'share' => 0.333],
        ]);
});

it('pages through a list without overlap when rows tie', function () {
    foreach (range(1, 5) as $index) {
        recordVisit('desktop', [step('view', ['from' => null]), press('click', "Tie {$index}", 'billboard', 0.5, 0.5)]);
    }

    $all = collect(interactions()->clicks(100))->pluck('target');
    $paged = collect([...interactions()->clicks(2, 0), ...interactions()->clicks(2, 2), ...interactions()->clicks(2, 4)])->pluck('target');

    expect($paged->all())->toBe($all->all())
        ->and($paged->unique())->toHaveCount(6);
});

it('shows each form\'s fields with the views that focused them and gave up on them', function () {
    expect(interactions()->forms())->toBe([
        [
            'form' => 'quote',
            'views' => 1,
            'submitted' => 0,
            'fields' => [
                ['field' => 'email', 'views' => 1, 'abandoned' => 1],
                ['field' => 'name', 'views' => 1, 'abandoned' => 0],
            ],
        ],
    ]);
});

it('follows views in and out of the page and the sources that started visits here', function () {
    $flows = interactions()->flows();

    expect($flows['previous'])->toBe([['previous' => null, 'views' => 3]])
        ->and($flows['next'])->toBe([['path' => '/gravel/frameset', 'views' => 1]])
        ->and(collect($flows['sources'])->pluck('referrer_host')->all())->toContain('www.google.com', null);
});

it('lists custom events and gives shares of views', function () {
    expect(interactions()->custom())->toBe([['name' => 'configurator_option', 'views' => 1, 'count' => 1, 'share' => 0.333]])
        ->and(interactions()->share('custom', 'configurator_option'))->toBe(0.333)
        ->and(interactions()->share('click'))->toBe(0.667)
        ->and(interactions()->share('rage', 'Nothing called this'))->toBe(0.0);
});

it('narrows every number and the map\'s points to one device', function () {
    expect(interactions('desktop')->figures()['views'])->toBe(2)
        ->and(interactions('desktop')->points())->toHaveCount(7)
        ->and(collect(interactions('mobile')->points())->pluck('target')->all())->toBe(['Build yours'])
        ->and(interactions('mobile')->points()[0])->toBe(['type' => 'click', 'section' => 'billboard', 'target' => 'Build yours', 'x' => 0.4, 'y' => 0.9])
        ->and(interactions('tablet')->figures()['views'])->toBe(0);
});

it('lists recent visits and replays one without dwell rows', function () {
    $visits = interactions()->visits();
    $replay = PageInteractions::visit(collect($visits)->firstWhere('referrer_host', 'www.google.com')['id']);

    expect($visits)->toHaveCount(3)
        ->and(collect($visits)->firstWhere('referrer_host', 'www.google.com')['length_ms'])->toBe(21_000)
        ->and(collect($replay['events'])->pluck('type'))->not->toContain('dwell')
        ->and($replay['events'][0]['type'])->toBe('view')
        ->and(end($replay['events'])['path'])->toBe('/gravel/frameset');
});

it('only matches the host when the URL names one', function () {
    expect(interactions(url: '//site.test/gravel')->figures()['views'])->toBe(3)
        ->and(interactions(url: '//site.pt/gravel')->figures()['views'])->toBe(0);
});
