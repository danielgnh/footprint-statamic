<?php

declare(strict_types=1);

use Danielgnh\FootprintStatamic\PageInteractions;
use Danielgnh\FootprintStatamic\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

uses(TestCase::class)->in(__DIR__);

const SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

/**
 * Posts one visit to Footprint's ingest endpoint the way a browser on the
 * site does. The Origin has to be the app URL's: test requests go to its host.
 *
 * @param  list<array<string, mixed>>  $events
 */
function recordVisit(string $device, array $events, ?string $referrer = null): TestResponse
{
    return ingest(batchOf($events, ['device' => $device, 'vw' => $device === 'mobile' ? 350 : 1400, 'ref' => $referrer]));
}

/**
 * @param  list<array<string, mixed>>  $events
 * @param  array<string, mixed>  $visit
 * @return array<string, mixed>
 */
function batchOf(array $events, array $visit = []): array
{
    return [
        'v' => 1,
        'visit' => ['id' => (string) Str::uuid(), 'ref' => null, 'utm' => [], 'device' => 'desktop', 'vw' => 1400, 'lang' => 'en', ...$visit],
        'events' => $events,
    ];
}

/**
 * @param  array<string, mixed>  $batch
 */
function ingest(array $batch): TestResponse
{
    return test()->call('POST', '/footprint/events', [], [], [], [
        'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        'HTTP_ORIGIN' => rtrim((string) config('app.url'), '/'),
        'HTTP_USER_AGENT' => SAFARI,
    ], json_encode($batch, JSON_THROW_ON_ERROR))->assertNoContent();
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function step(string $type, array $fields = []): array
{
    return ['type' => $type, 't' => 100, 'view' => 1, 'path' => '/gravel', ...$fields];
}

/**
 * @return array<string, mixed>
 */
function press(string $type, string $target, string $section, float $x, float $y): array
{
    return step($type, ['target' => $target, 'section' => $section, 'x' => $x, 'y' => $y, ...($type === 'click' ? ['href' => '/gravel/build'] : [])]);
}

/**
 * Three views of /gravel: two on desktop, one on a phone. Only the first
 * reaches the story, scrolls to the end, gives up on the quote form and
 * moves on to the frameset.
 */
function recordGravelPage(): void
{
    recordVisit('desktop', [
        step('view', ['from' => null]),
        step('section', ['section' => 'billboard', 'i' => 1]),
        step('section', ['section' => 'story', 'i' => 2]),
        step('scroll', ['value' => 50]),
        step('scroll', ['value' => 100]),
        press('click', 'Build yours', 'billboard', 0.5, 0.5),
        step('field', ['target' => 'email', 'form' => 'quote']),
        step('field', ['target' => 'name', 'form' => 'quote']),
        step('field', ['target' => 'email', 'form' => 'quote']),
        step('abandon', ['target' => 'email', 'form' => 'quote']),
        step('custom', ['name' => 'configurator_option', 'props' => ['part' => 'wheels']]),
        step('dwell', ['section' => null, 'value' => 20_000]),
        step('dwell', ['section' => 'billboard', 'value' => 5_000]),
        step('dwell', ['section' => 'billboard', 'value' => 3_000]),
        step('dwell', ['section' => 'story', 'value' => 6_000]),
        step('view', ['t' => 21_000, 'view' => 2, 'path' => '/gravel/frameset', 'from' => '/gravel']),
    ], 'www.google.com');

    recordVisit('desktop', [
        step('view', ['from' => null]),
        step('section', ['section' => 'billboard', 'i' => 1]),
        step('scroll', ['value' => 50]),
        ...array_fill(0, 5, press('rage', 'Ride it first', 'billboard', 0.2, 0.8)),
        press('dead', 'Four paints', 'paints', 0.1, 0.1),
        step('dwell', ['section' => null, 'value' => 10_000]),
        step('dwell', ['section' => 'billboard', 'value' => 4_000]),
    ]);

    recordVisit('mobile', [
        step('view', ['from' => null]),
        step('section', ['section' => 'billboard', 'i' => 1]),
        press('click', 'Build yours', 'billboard', 0.4, 0.9),
        step('dwell', ['section' => null, 'value' => 30_000]),
    ]);
}

function interactions(?string $device = null, string $url = '/gravel'): PageInteractions
{
    return new PageInteractions($url, now()->subDay(), now()->addMinute(), $device);
}

function pages(): void
{
    Collection::make('pages')->routes('{slug}')->save();
    Collection::make('faqs')->save();

    Entry::make()->collection('pages')->id('gravel')->slug('gravel')->data(['title' => 'Gravel'])->save();
    Entry::make()->collection('faqs')->id('faq')->slug('faq')->data(['title' => 'A question'])->save();
}

function editor(bool $permitted = true): UserContract
{
    $user = User::make()->email(Str::random(8).'@site.test');

    if ($permitted) {
        $user->makeSuper();
    }

    return tap($user)->save();
}

/**
 * Statamic only adds the tab while the control panel is asking, and caches
 * blueprints for the request.
 */
function inControlPanel(): void
{
    app()->instance('request', Request::create('/cp/collections/pages/entries/gravel'));
    Blink::flush();
}

/**
 * @return array<string, mixed>
 */
function preload(EntryContract $entry): array
{
    return $entry->blueprint()->field('footprint_interactions')->setParent($entry)->fieldtype()->preload();
}
