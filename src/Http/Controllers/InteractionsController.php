<?php

namespace Danielgnh\FootprintStatamic\Http\Controllers;

use Danielgnh\FootprintStatamic\Page;
use Danielgnh\FootprintStatamic\PageInteractions;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Entries\Entry;
use Statamic\Http\Controllers\CP\CpController;

/**
 * One part of an entry's Interactions tab per request, so each panel loads
 * when it nears the viewport and caches on its own.
 */
class InteractionsController extends CpController
{
    public const PARTS = ['figures', 'sections', 'scroll', 'clicks', 'frustration', 'forms', 'flows', 'points', 'visits'];

    public const PAGED = ['clicks', 'frustration', 'visits'];

    public const PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, Entry $entry, string $part): array
    {
        $this->authorize('view interactions');

        $page = Page::of($entry) ?? abort(404);
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,30,90'],
            'device' => ['nullable', 'in:mobile,tablet,desktop'],
            'since' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $number = in_array($part, self::PAGED, true) ? (int) (data_get($filters, 'page', 1)) : null;

        return Cache::remember(
            'footprint-statamic:'.md5(serialize([$part, $page->query(), Arr::except($filters, 'page'), $number])),
            (int) config('footprint-statamic.cache'),
            fn (): array => $this->answer($part, $this->report($page, $filters), $number, $filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function answer(string $part, PageInteractions $report, ?int $page, array $filters): array
    {
        if ($page !== null) {
            $rows = $report->{$part}(self::PER_PAGE + 1, ($page - 1) * self::PER_PAGE);

            return (new Paginator($rows, self::PER_PAGE, $page))->withQueryString()->toArray();
        }

        if ($part === 'points' && blank(data_get($filters, 'device'))) {
            return ['data' => []];
        }

        return ['data' => $report->{$part}()];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function report(Page $page, array $filters): PageInteractions
    {
        $from = now()->subDays((int) (data_get($filters, 'days', 30)))->startOfDay();
        $since = data_get($filters, 'since');

        return new PageInteractions(
            url: $page->query(),
            from: filled($since) ? $from->max(Carbon::parse($since)) : $from,
            to: today()->addDay(),
            device: data_get($filters, 'device'),
        );
    }
}
