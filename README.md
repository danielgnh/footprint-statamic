# Footprint for Statamic

An Interactions tab on Statamic 6 entries, from [`danielgnh/footprint`](https://github.com/danielgnh/footprint): how visitors use each entry's page, next to the content an editor is changing.

- **Figures**: views, visits, median time on page, the views that scrolled past half and to the end, and the views with rage clicks, dead clicks or a form given up.
- **Sections**: every section in page order with the views that reached it and the median time it stayed on screen, plus scroll depth.
- **The page**: the live page in a frame at a device's real width, with attention, click and frustration layers drawn over the sections they belong to.
- **Lists** carrying every number the picture shows: most clicked, frustration, each form's fields, where views came from and went next.
- **Visit playback**: one visit's recorded events replayed over today's page, labelled as reconstructed, never a recording.

Every count gives a view one vote, so one visitor hammering a button cannot top a list. Below 30 views the tab shows counts instead of shares.

## Install

```bash
composer require danielgnh/footprint-statamic
php artisan migrate
```

The control panel bundle ships prebuilt and is published on `statamic:install`, which Statamic runs after every `composer install`; the site needs no Vite setup for it. If the tab stays blank, run `php artisan vendor:publish --tag=footprint-statamic --force`.

Render the tracker for guests only, so editors, Live Preview and the tab's own frame never record:

```blade
@guest
    @footprint
@endguest
```

Super users see the tab. On Statamic Pro, give other roles the **View page interactions** permission.

## Configuration

`php artisan vendor:publish --tag=footprint-statamic-config` publishes `config/footprint-statamic.php`.

| Key | Default | Meaning |
|---|---|---|
| `collections` | `null` | Collections whose entries get the tab. Null is every collection whose entries have a URL. |
| `cache` | `600` | Seconds each part of the tab is cached for one entry, range and device. |
| `events` | `[]` | Custom event names AI agents may see through the `footprint-statamic-mcp` tools. |

When the front end serves entries somewhere other than `$entry->url()`, map them in a service provider:

```php
use Danielgnh\FootprintStatamic\Page;
use Statamic\Contracts\Entries\Entry;

Page::urlUsing(fn (Entry $entry) => "/bikes/{$entry->slug()}");
```

The frame loads the site-relative URL, so it stays on the control panel's origin whatever `APP_URL` says. On a multisite whose sites have their own domains, each site's numbers stay apart, and a page on another domain than the control panel shows the lists without the map: a browser forbids reading a cross-origin frame.

## Demo traffic

On a local site, headless visitors give the tab something to show:

```bash
npm install --save-dev playwright && npx playwright install chromium
php artisan footprint:visitors https://site.test/gravel --visits=30 --device=desktop --device=mobile
```

They scroll part or all of the way, click controls that stay on the site, sometimes hammer a heading or touch a form, and leave. The command only runs in the `local` environment, since the visits land in the real tables.

## Endpoints

Each panel of the tab fetches its own part when it nears the viewport, from `GET /cp/footprint/entries/{entry}/{part}` behind the `view interactions` permission. Every part takes `days` (7, 30 or 90; default 30), `device` (`mobile`, `tablet` or `desktop`; default all) and `since` (a date the range starts at, at the latest, such as the entry's last edit). Answers are plain JSON under `data`; shares run from 0 to 1 and are of the page's views.

| Part | `data` |
|---|---|
| `figures` | `{views, visits, median_time_ms, scrolled_half, scrolled_all, rage, dead, abandons}`, the last five each `{views, share}` |
| `sections` | `[{section, position, views, reach, median_ms}]` in page order |
| `scroll` | `[{depth, views, share}]` for every 10% |
| `clicks` | `[{target, section, href, views, clicks, share}]`, paged |
| `frustration` | `[{type, target, section, views, count, share}]`, paged |
| `forms` | `[{form, views, submitted, fields: [{field, views, abandoned}]}]` |
| `flows` | `{previous: [{previous, views}], next: [{path, views}], sources: [{referrer_host, utm_source, utm_campaign, visits}]}` |
| `points` | `[{type, section, target, x, y}]`, newest first, at most 5,000; empty without a `device` |
| `visits` | `[{id, device, viewport, lang, referrer_host, utm_source, views, started_at, ended_at, length_ms}]`, paged |

Paged parts take `page` and answer as Laravel's simple paginator: `data`, `current_page`, `next_page_url` (null on the last page) and `per_page` (15). Rows rank by the views that had them, then by raw events, then by their names, so pages never overlap.

`GET /cp/footprint/visits/{visit}` answers `{data: {visit, events}}`: one visit and its events in order, without dwell rows.

The numbers come from `Danielgnh\FootprintStatamic\PageInteractions`, which takes a path or a full URL, a range and an optional device, and returns plain arrays. Build a widget or a report on it rather than querying Footprint's tables again.

## AI agents (Laravel Boost)

The package ships two Boost skills in `resources/boost/skills/`:

- **`footprint-statamic-interactions`**: installing and adapting the tab, naming sections and controls so the data reads well, demo traffic, testing it in the app and reading its numbers.
- **`footprint-statamic-mcp`**: four read-only tools and a prompt for the site's [Statamic MCP](https://github.com/danielgnh/statamic-mcp) server, on the same `PageInteractions`, so an agent can find pages that underperform, see why, change them through the entries tools and measure the change. The agent only gets aggregates of names the live page contains.

List the package under `packages` and the skills under `skills` in `boost.json`, then run `php artisan boost:install`.

## Development

```bash
composer install && npm install
composer test        # rector, pint, phpstan, pest
npm test             # vitest
npm run build        # the control panel bundle into resources/dist
```

The overlay imports the tracker's own section rules from the installed footprint package, so the two cannot disagree. Commit `resources/dist` after a build.

Statamic declares the addon layers before its own utilities, so a utility the control panel also uses beats any variant of it in an addon. A `dark:` or breakpoint override of `bg-gray-50`, `text-gray-500`, `hidden` or `grid-cols-2` never applies. Use tones that read in both modes (`text-gray-500`, `bg-gray-500/10`) and arbitrary values for layout (`grid-cols-[repeat(auto-fill,minmax(11rem,1fr))]`).
