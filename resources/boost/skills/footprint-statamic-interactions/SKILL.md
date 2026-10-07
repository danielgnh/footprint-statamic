---
name: footprint-statamic-interactions
description: Set up and adapt danielgnh/footprint-statamic, the Interactions tab on Statamic 6 entries that shows how visitors use each entry's page (figures, section funnel, click and frustration map over the live page, forms, flows, visit playback). Covers which collections get the tab, mapping entries to the URLs visitors load, naming sections and controls so the data reads well, checking it with demo traffic, testing it in the app, and reading its numbers. Use when someone wants Footprint data in the Statamic control panel, or the tab shows nothing or the wrong thing.
---

# Footprint: the Interactions tab on Statamic entries

## When to use this skill

The tab ships in `danielgnh/footprint-statamic`: the fieldtype, the listener that adds it, the permission, the endpoints, the query class and the prebuilt control-panel bundle. Do not rebuild any of it in the app. The work left on a site is to install it, tell it which entries have pages and where they are served, and mark up the front end so the names in the data mean something.

An app that has a hand-built tab from an earlier version of this skill (`App\Footprint\PageInteractions`, `App\Http\Controllers\CP\InteractionsController`, `App\Fieldtypes\Interactions`, `App\Listeners\AddInteractionsTab`, `resources/js/cp/*`, `vite-cp.config.js`): delete those files, their tests, the `Statamic::vite` call and the `cp:*` scripts once the addon's tab shows, and keep the app's markup.

## Install

```bash
composer require danielgnh/footprint-statamic
php artisan migrate
```

- `statamic:install` runs on `composer install` and publishes the tab's bundle to `public/vendor/footprint-statamic`. If the tab stays blank, run `php artisan vendor:publish --tag=footprint-statamic --force`.
- The tracker goes in the front-end layout for guests only, so editors, Live Preview and the tab's own frame record nothing: `@guest @footprint @endguest`. Statamic signs editors in on the `web` guard, so an editor is never a guest on the front end. If the control panel uses its own guard, test `Statamic\Facades\User::current()` instead.
- Super users see the tab. On Statamic Pro, give other roles `view interactions`; Solo allows a single user.

## Adapt it to the site

- **Collections.** By default every collection whose entries have a route gets the tab. To narrow it, publish the config (`php artisan vendor:publish --tag=footprint-statamic-config`) and list them: `'collections' => ['pages', 'articles']`.
- **URLs.** The tab reads the page at `$entry->url()`, site-relative, so the frame stays on the control panel's origin whatever `APP_URL` says. When the front end serves entries somewhere else (custom Laravel routes, a SPA router), map them in `AppServiceProvider::boot()`:

  ```php
  use Danielgnh\FootprintStatamic\Page;
  use Statamic\Contracts\Entries\Entry;

  Page::urlUsing(fn (Entry $entry) => $entry->collectionHandle() === 'bikes' ? "/bikes/{$entry->slug()}" : $entry->url());
  ```

- **Localisations** each show their own language's page and visitors, because editing a localisation opens its own entry.
- **Multisite on several domains**: the numbers stay apart per domain. A page on another domain than the control panel cannot be drawn over (the browser forbids reading a cross-origin frame), so the tab says so and shows the lists only.

## Name the page

The tab is only as clear as the names the tracker records. Footprint's Boost guideline has the rules; on a Statamic site they come down to:

- Every page builder set renders inside a `data-footprint-section` named after its set handle, slugged, and a set used twice on a page is numbered (`faq`, `faq-2`). The MCP tools tie sections back to sets by exactly this name.
- The site header, the footer and every dialog carry their own `data-footprint-section`. An article's inner `<header>` is not a section, so it never collides with the site's.
- `data-footprint` goes on any control whose text has several parts or changes: tabs, cards, list items, anything with a date, a price or a count. Without it a card reads "28 Jun 2026 Gravel Challenge Lisbon…" and changes with the content.
- Every field has a `name`, and every form a `data-footprint` (Statamic's `{{ form:create }}` gives fields a name; Inertia, React and Vue forms often do not). Otherwise a field is named after its label, required mark included ("Email address*"), and the form shows as unnamed.
- A name is history: renaming a section or a `data-footprint` splits its data into two rows from that day on. Pick names once, and when one must change, tell editors to count from that day.
- A fixed or sticky section (sticky header, reveal footer, fixed bar) records clicks but no reach or time, unless it is a dialog. The map leaves it out; its clicks are in the lists.
- Pop-ups and banners that open by themselves must not open when `window.self !== window.top`, or they cover the tab's frame and Live Preview.

## See it with data

On a local site, send headless visitors through a page, then open its entry:

```bash
npm install --save-dev playwright && npx playwright install chromium
php artisan footprint:visitors https://site.test/gravel --visits=30
```

They scroll, click, sometimes hammer a heading or touch a form, across desktop, tablet and mobile. The command refuses to run outside the `local` environment: the visits are made up and land in the real tables. Empty them afterwards with `Danielgnh\Footprint\Models\Visit::query()->delete()` and the same on `Footprint` if the site will go live with that database.

## Done when

- `php artisan test` passes, including the app's own tests below.
- An entry of each listed collection has an Interactions tab; one of an unlisted collection has none.
- The frame loads the page at the chosen device's width, and the attention layer's outlines sit on the sections they name. A section in the funnel marked "Not on the page" was renamed or removed.
- Opening the tab and switching devices writes no new rows: compare `Visit::count()` before and after.
- On a new install, Statamic's licence notice covers the control panel in trial mode until it is dismissed; close it before checking the tab in a browser.

## Reading the numbers

- Every count gives a view one vote: "views with rage clicks", "views that clicked it", "focused in". A visitor hammering a button counts once, so one person cannot top the frustration list. Raw presses and clicks sit next to them for context.
- Below 30 views the tab shows counts instead of shares and says the sample is small. Never present a pattern from a handful of visits as a finding.
- The frame is today's page; the data may be older. When the entry was edited inside the range, the tab offers to count from that edit.
- Click positions belong to one layout, so the map shows one device at a time.
- Playback is reconstructed from recorded events. Footprint records no screen and no DOM, and the page may have changed since.

## Testing in the app

The addon tests itself. The app tests its own side: the tracker is guest-only, its sections carry the names the data needs, and the tab reaches its collections.

```php
<?php

use Illuminate\Http\Request;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Http\Middleware\PingOutpost;

// Control panel requests ping Statamic's licensing server: seconds each.
beforeEach(fn () => $this->withoutMiddleware(PingOutpost::class));

it('renders the tracker for visitors and never for an editor', function () {
    $this->get('/gravel')->assertSee('/footprint/footprint.js', false);

    $this->actingAs(tap(User::make()->email('editor@site.test')->makeSuper())->save())
        ->get('/gravel')
        ->assertDontSee('/footprint/footprint.js', false);
});

it('names every section of the page', function () {
    $this->get('/gravel')
        ->assertSee('data-footprint-section="hero"', false)
        ->assertSee('data-footprint-section="faq"', false);
});

it('gives the bikes their tab', function () {
    // The tab is added while the control panel asks, and blueprints are cached per request.
    app()->instance('request', Request::create('/cp/collections/bikes/entries/gravel'));
    Blink::flush();

    expect(Entry::find('gravel')->blueprint()->hasField('footprint_interactions'))->toBeTrue();
});
```

- To seed Footprint data, post batches to its ingest endpoint with `HTTP_ORIGIN => config('app.url')`: test requests go to the app URL's host, and any other Origin gets a 403. Use a browser user agent; bots are dropped.
- A test with a second, non-super user needs `config(['statamic.editions.pro' => true])`: Solo refuses a second user.
- Do not name Pest helpers `event()`, `report()` or anything else Laravel defines globally.

## Building on the data

`Danielgnh\FootprintStatamic\PageInteractions` is the query class behind the tab: `new PageInteractions($url, $from, $to, $device)` with a path or a full URL, and methods that return plain arrays (`figures`, `sections`, `scroll`, `clicks`, `frustration`, `forms`, `flows`, `custom`, `points`, `visits`, `share`). Use it for a widget, a report or a command instead of querying Footprint's tables again. The addon's README documents the endpoints' JSON. The `footprint-statamic-mcp` skill gives the same numbers to AI agents.

## Rules

- Never join Footprint data with anything that identifies a person: users, form submissions, orders, logs with IPs. A visit is shown by its device, width, length, language and referrer host, never by its id.
- The tab only reads. Nothing in the app should write to Footprint's tables or record an editor's own use.
- Playback is labelled as reconstructed from recorded events wherever it appears.
