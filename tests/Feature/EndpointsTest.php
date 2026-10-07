<?php

use Danielgnh\Footprint\Models\Footprint;
use Danielgnh\Footprint\Models\Visit;
use Danielgnh\FootprintStatamic\Http\Controllers\InteractionsController;

beforeEach(fn () => pages());

function part(string $part, array $query = []): string
{
    return cp_route('footprint.interactions.show', ['entry' => 'gravel', 'part' => $part, ...$query]);
}

it('answers each part in its own shape', function (string $part, array $structure) {
    recordGravelPage();

    $this->actingAs(editor())
        ->getJson(part($part, ['device' => 'desktop']))
        ->assertOk()
        ->assertJsonStructure($structure);
})->with([
    'figures' => ['figures', ['data' => ['views', 'visits', 'median_time_ms', 'scrolled_half' => ['views', 'share'], 'scrolled_all' => ['views', 'share'], 'rage' => ['views', 'share'], 'dead' => ['views', 'share'], 'abandons' => ['views', 'share']]]],
    'sections' => ['sections', ['data' => ['*' => ['section', 'position', 'views', 'reach', 'median_ms']]]],
    'scroll' => ['scroll', ['data' => ['*' => ['depth', 'views', 'share']]]],
    'clicks' => ['clicks', ['data' => ['*' => ['target', 'section', 'href', 'views', 'clicks', 'share']], 'current_page', 'next_page_url', 'per_page']],
    'frustration' => ['frustration', ['data' => ['*' => ['type', 'target', 'section', 'views', 'count', 'share']], 'current_page', 'next_page_url']],
    'forms' => ['forms', ['data' => ['*' => ['form', 'views', 'submitted', 'fields' => ['*' => ['field', 'views', 'abandoned']]]]]],
    'flows' => ['flows', ['data' => ['previous' => ['*' => ['previous', 'views']], 'next', 'sources' => ['*' => ['referrer_host', 'utm_source', 'utm_campaign', 'visits']]]]],
    'points' => ['points', ['data' => ['*' => ['type', 'section', 'target', 'x', 'y']]]],
    'visits' => ['visits', ['data' => ['*' => ['id', 'device', 'viewport', 'lang', 'referrer_host', 'utm_source', 'views', 'started_at', 'ended_at', 'length_ms']], 'current_page', 'next_page_url']],
]);

it('reads the entry\'s page, not a URL from the browser', function () {
    recordGravelPage();

    $this->actingAs(editor())
        ->getJson(part('figures', ['device' => 'desktop', 'url' => 'https://elsewhere.test/other']))
        ->assertOk()
        ->assertJsonPath('data.views', 2);
});

it('draws points for one device only', function () {
    recordGravelPage();

    $this->actingAs(editor())->getJson(part('points'))->assertOk()->assertExactJson(['data' => []]);
    $this->actingAs(editor())->getJson(part('points', ['device' => 'mobile']))->assertOk()->assertJsonCount(1, 'data');
});

it('can start counting at the last edit', function () {
    recordGravelPage();

    $this->actingAs(editor())
        ->getJson(part('figures', ['since' => now()->addHour()->toIso8601String()]))
        ->assertOk()
        ->assertJsonPath('data.views', 0);
});

it('sends the lists a page at a time', function () {
    foreach (range(1, InteractionsController::PER_PAGE + 2) as $index) {
        recordVisit('desktop', [step('view', ['from' => null]), press('click', "Button {$index}", 'billboard', 0.5, 0.5)]);
    }

    $page = fn (string $part, int $number) => $this->actingAs(editor())->getJson(part($part, ['page' => $number]))->assertOk();

    $first = $page('visits', 1)->assertJsonCount(InteractionsController::PER_PAGE, 'data')->assertJsonPath('current_page', 1);
    $second = $page('visits', 2)->assertJsonCount(2, 'data')->assertJsonPath('next_page_url', null);

    expect($first->json('next_page_url'))->toContain('page=2')
        ->and(collect([...$first->json('data'), ...$second->json('data')])->pluck('id')->unique())->toHaveCount(InteractionsController::PER_PAGE + 2)
        ->and($page('clicks', 2)->json('data'))->toHaveCount(2);
});

it('replays a single visit', function () {
    recordGravelPage();

    $id = interactions()->visits()[0]['id'];

    $this->actingAs(editor())
        ->getJson(cp_route('footprint.visits.show', ['visit' => $id]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['visit' => ['device', 'viewport', 'lang', 'referrer_host', 'started_at', 'ended_at'], 'events' => ['*' => ['view', 't', 'path', 'type']]]]);

    $this->actingAs(editor())->getJson(cp_route('footprint.visits.show', ['visit' => 'no-such-visit']))->assertNotFound();
});

it('validates its filters and knows its parts', function () {
    $this->actingAs(editor())
        ->getJson(part('figures', ['days' => 14, 'device' => 'watch', 'since' => 'soon']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days', 'device', 'since']);

    $this->actingAs(editor())->getJson('/cp/footprint/entries/gravel/everything')->assertNotFound();
    $this->actingAs(editor())->getJson(cp_route('footprint.interactions.show', ['entry' => 'missing', 'part' => 'figures']))->assertNotFound();
});

it('reads back the same answer from a cache that unserializes no objects', function () {
    expect(config('cache.serializable_classes'))->not->toBeTrue()
        ->and(config('cache.stores.array.serialize'))->toBeTrue();

    recordGravelPage();

    foreach (InteractionsController::PARTS as $part) {
        $answer = fn () => $this->actingAs(editor())->getJson(part($part, ['device' => 'desktop']))->assertOk()->json();

        expect($answer())->toBe($answer());
    }

    $this->actingAs(editor())->getJson(part('visits'))->assertJsonCount(3, 'data');
});

it('lets nobody without the permission read the data', function () {
    $this->getJson(part('figures'))->assertUnauthorized();

    $this->actingAs(editor(permitted: false))->getJson(part('figures'))->assertForbidden();
    $this->actingAs(editor(permitted: false))->getJson(cp_route('footprint.visits.show', ['visit' => 'any']))->assertForbidden();
});

it('writes nothing while the tab is read', function () {
    recordGravelPage();
    $rows = [Visit::count(), Footprint::count()];

    foreach (InteractionsController::PARTS as $part) {
        $this->actingAs(editor())->getJson(part($part, ['device' => 'desktop']))->assertOk();
    }

    expect([Visit::count(), Footprint::count()])->toBe($rows);
});
