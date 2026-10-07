<?php

namespace Danielgnh\FootprintStatamic;

use Carbon\CarbonInterface;
use Danielgnh\Footprint\Builders\FootprintBuilder;
use Danielgnh\Footprint\Builders\VisitBuilder;
use Danielgnh\Footprint\Models\Footprint;
use Danielgnh\Footprint\Models\Visit;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Every number of one page over a range and an optional device, as plain
 * arrays, so they cache and serialise as they are. Counts give every view
 * one vote: a view that clicked a button 300 times counts once.
 */
final class PageInteractions
{
    public const int POINTS = 5000;

    private ?int $views = null;

    /** @var Collection<int, \stdClass>|null */
    private ?Collection $dwell = null;

    /**
     * @param  string  $url  a path or a full URL; a URL with a host only matches that host
     */
    public function __construct(
        private readonly string $url,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
        private readonly ?string $device = null,
    ) {}

    /**
     * @return array{views: int, visits: int, median_time_ms: int, scrolled_half: array{views: int, share: float}, scrolled_all: array{views: int, share: float}, rage: array{views: int, share: float}, dead: array{views: int, share: float}, abandons: array{views: int, share: float}}
     */
    public function figures(): array
    {
        $scroll = collect($this->scroll())->keyBy('depth');

        return [
            'views' => $this->views(),
            'visits' => $this->events()->ofType('view')->distinct()->count('visit_id'),
            'median_time_ms' => (int) $this->dwell()->whereNull('section')->median('ms'),
            'scrolled_half' => $this->ofViews((int) data_get($scroll->get(50), 'views', 0)),
            'scrolled_all' => $this->ofViews((int) data_get($scroll->get(100), 'views', 0)),
            'rage' => $this->ofViews($this->viewsWith('rage')),
            'dead' => $this->ofViews($this->viewsWith('dead')),
            'abandons' => $this->ofViews($this->viewsWith('abandon')),
        ];
    }

    /**
     * Share of views with at least one event of the type, 0 to 1. The name
     * narrows to one form for submit and one target for any other type, so
     * share('submit', 'quote') is the share of views that sent the quote form.
     */
    public function share(string $type, ?string $name = null): float
    {
        return $this->ofViews($this->viewsWith($type, $name))['share'];
    }

    /**
     * Sections in page order: the views that reached each one, their share of
     * the page's views, and the median time it stayed on screen in them.
     *
     * @return array<int, array{section: string, position: float, views: int, reach: float, median_ms: int}>
     */
    public function sections(): array
    {
        $dwell = $this->dwell()->whereNotNull('section')->groupBy('section');

        return $this->query()
            ->fromSub($this->events()->reachedSections()->toBase(), 'reached')
            ->select('section')
            ->selectRaw('count(*) as views, avg(position) as position')
            ->groupBy('section')
            ->get()
            ->map(fn (object $row): array => [
                'section' => (string) data_get($row, 'section'),
                'position' => (float) data_get($row, 'position'),
                'views' => (int) data_get($row, 'views'),
                'reach' => $this->ofViews((int) data_get($row, 'views'))['share'],
                'median_ms' => (int) collect($dwell->get(data_get($row, 'section')))->median('ms'),
            ])
            ->sortBy('position')
            ->values()
            ->all();
    }

    /**
     * Views whose deepest point reached each 10% step. A view that never
     * passed 10% has no scroll row and counts nowhere.
     *
     * @return array<int, array{depth: int, views: int, share: float}>
     */
    public function scroll(): array
    {
        $depths = $this->events()->deepestScroll()->toBase()->pluck('depth');

        return collect(range(10, 100, 10))
            ->map(fn (int $depth): array => ['depth' => $depth, ...$this->ofViews($depths->filter(fn ($deepest): bool => $deepest >= $depth)->count())])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>> target, section, href, views, clicks and share
     */
    public function clicks(int $limit = 30, int $offset = 0): array
    {
        return $this->ranked(['click'], ['target', 'section', 'href'], 'clicks', $limit, $offset);
    }

    /**
     * @return array<int, array<string, mixed>> type, target, section, views, count and share
     */
    public function frustration(int $limit = 30, int $offset = 0): array
    {
        return $this->ranked(['rage', 'dead'], ['type', 'target', 'section'], 'count', $limit, $offset);
    }

    /**
     * Custom events sent with footprint.track(), by name.
     *
     * @return array<int, array{name: string, views: int, count: int, share: float}>
     */
    public function custom(int $limit = 20): array
    {
        return collect($this->ranked(['custom'], ['target'], 'count', $limit))
            ->map(fn (array $row): array => [
                'name' => (string) data_get($row, 'target'),
                'views' => (int) data_get($row, 'views'),
                'count' => (int) data_get($row, 'count'),
                'share' => (float) data_get($row, 'share'),
            ])
            ->all();
    }

    /**
     * Click positions for the map, newest first. They belong to one layout,
     * so they only mean something for one device.
     *
     * @return array<int, array<string, mixed>> type, section, target, x and y
     */
    public function points(): array
    {
        return $this->events()
            ->ofType('click', 'rage', 'dead')
            ->whereNotNull('section')
            ->whereNotNull('x')
            ->select('type', 'section', 'target', 'x', 'y')
            ->latest('id')
            ->limit(self::POINTS)
            ->toBase()
            ->get()
            ->map(fn (object $point): array => [...(array) $point, 'x' => (float) data_get($point, 'x'), 'y' => (float) data_get($point, 'y')])
            ->all();
    }

    /**
     * Each form with the views that touched it and sent it, and its fields
     * with the views that focused them and the views that ended on them
     * without sending the form.
     *
     * @return array<int, array{form: ?string, views: int, submitted: int, fields: array<int, array{field: ?string, views: int, abandoned: int}>}>
     */
    public function forms(): array
    {
        $touched = $this->viewsBy(['field'], ['form']);
        $submitted = $this->viewsBy(['submit'], ['form']);
        $abandoned = $this->viewsBy(['abandon'], ['form', 'target']);

        return $this->viewsBy(['field'], ['form', 'target'])
            ->groupBy(fn (object $field): string => (string) data_get($field, 'form'))
            ->map(fn (Collection $fields, string $form): array => [
                'form' => $form === '' ? null : $form,
                'views' => (int) data_get($touched->get($form), 'views', 0),
                'submitted' => (int) data_get($submitted->get($form), 'views', 0),
                'fields' => $fields
                    ->map(fn (object $field): array => [
                        'field' => data_get($field, 'target'),
                        'views' => (int) data_get($field, 'views'),
                        'abandoned' => (int) data_get($abandoned->get($this->key($field, ['form', 'target'])), 'views', 0),
                    ])
                    ->sortByDesc('views')
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('views')
            ->values()
            ->all();
    }

    /**
     * Where views of this page came from inside the site, where they went
     * next, and which outside sources started visits here.
     *
     * @return array{previous: array<int, array{previous: ?string, views: int}>, next: array<int, array{path: string, views: int}>, sources: array<int, array<string, mixed>>}
     */
    public function flows(): array
    {
        $previous = $this->events()
            ->ofType('view')
            ->select('previous')
            ->selectRaw('count(*) as views')
            ->groupBy('previous')
            ->orderByDesc('views')
            ->orderBy('previous')
            ->limit(10)
            ->toBase()
            ->get();

        $next = $this->events()
            ->ofType('view')
            ->join('footprints as following', function (JoinClause $join): void {
                $join->on('following.visit_id', '=', 'footprints.visit_id')
                    ->on('following.view', '=', $join->raw('footprints.view + 1'))
                    ->where('following.type', '=', 'view');
            })
            ->selectRaw('following.path as path, count(*) as views')
            ->groupBy('following.path')
            ->orderByDesc('views')
            ->orderBy('following.path')
            ->limit(10)
            ->toBase()
            ->get();

        $sources = Visit::landedOn($this->url)
            ->between($this->from, $this->to)
            ->when($this->device, fn (VisitBuilder $visits, string $device) => $visits->onDevice($device))
            ->select('referrer_host', 'utm_source', 'utm_campaign')
            ->selectRaw('count(*) as visits')
            ->groupBy('referrer_host', 'utm_source', 'utm_campaign')
            ->orderByDesc('visits')
            ->orderBy('referrer_host')
            ->limit(10)
            ->toBase()
            ->get();

        return [
            'previous' => $previous->map(fn (object $row): array => ['previous' => data_get($row, 'previous'), 'views' => (int) data_get($row, 'views')])->all(),
            'next' => $next->map(fn (object $row): array => ['path' => (string) data_get($row, 'path'), 'views' => (int) data_get($row, 'views')])->all(),
            'sources' => $sources->map(fn (object $row): array => [...(array) $row, 'visits' => (int) data_get($row, 'visits')])->all(),
        ];
    }

    /**
     * Recent visits that viewed this page, newest first. `length_ms` comes
     * from the events' own clock: a visit under 15 seconds arrives in one
     * batch, so its server times are equal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visits(int $limit = 50, int $offset = 0): array
    {
        return Visit::query()
            ->whereIn('id', $this->events()->ofType('view')->select('visit_id'))
            ->latest('started_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->select('id', 'device', 'viewport', 'lang', 'referrer_host', 'utm_source', 'views', 'started_at', 'ended_at')
            ->addSelect(['length_ms' => Footprint::query()->selectRaw('max(t)')->whereColumn('visit_id', 'footprint_visits.id')])
            ->withCasts(['length_ms' => 'integer'])
            ->get()
            ->toArray();
    }

    /**
     * One visit and its events in order, without dwell: those rows are
     * accounting sent on every flush, not something the visitor did.
     *
     * @return array{visit: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function visit(string $id): array
    {
        $visit = Visit::query()->findOrFail($id, ['id', 'device', 'viewport', 'lang', 'referrer_host', 'started_at', 'ended_at']);

        return [
            'visit' => $visit->toArray(),
            'events' => $visit->footprints()
                ->timeline()
                ->get(['view', 't', 'path', 'type', 'section', 'target', 'href', 'previous', 'form', 'x', 'y', 'value', 'data'])
                ->toArray(),
        ];
    }

    /**
     * A fresh query for the page's events in the range and device.
     */
    public function events(): FootprintBuilder
    {
        return Footprint::onPage($this->url)
            ->between($this->from, $this->to)
            ->when($this->device, fn (FootprintBuilder $events, string $device) => $events->onDevice($device));
    }

    /**
     * Rows per value of the columns, ranked by the views that had them and
     * then by raw events, with the columns breaking ties so pages of the
     * list never overlap.
     *
     * @param  list<string>  $types
     * @param  list<string>  $columns
     * @param  'clicks'|'count'  $count
     * @return array<int, array<string, mixed>>
     */
    private function ranked(array $types, array $columns, string $count, int $limit, int $offset = 0): array
    {
        return $this->query()
            ->fromSub($this->events()->ofType(...$types)->perView(...$columns)->selectRaw('count(*) as events')->toBase(), 'per_view')
            ->select($columns)
            ->selectRaw("count(*) as views, sum(events) as {$count}")
            ->groupBy($columns)
            ->orderByDesc('views')
            ->orderByDesc($count)
            ->tap(fn (Query $query) => collect($columns)->each(fn (string $column) => $query->orderBy($column)))
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                ...(array) $row,
                'views' => (int) data_get($row, 'views'),
                $count => (int) data_get($row, $count),
                'share' => $this->ofViews((int) data_get($row, 'views'))['share'],
            ])
            ->all();
    }

    private function viewsWith(string $type, ?string $name = null): int
    {
        return $this->query()
            ->fromSub(
                $this->events()
                    ->ofType($type)
                    ->when($name !== null, fn (FootprintBuilder $events) => $events->where($type === 'submit' ? 'form' : 'target', $name))
                    ->perView()
                    ->toBase(),
                'per_view',
            )
            ->count();
    }

    /**
     * Views per value of the columns, keyed like key().
     *
     * @param  list<string>  $types
     * @param  list<string>  $columns
     * @return Collection<string, \stdClass>
     */
    private function viewsBy(array $types, array $columns): Collection
    {
        return $this->query()
            ->fromSub($this->events()->ofType(...$types)->perView(...$columns)->toBase(), 'per_view')
            ->select($columns)
            ->selectRaw('count(*) as views')
            ->groupBy($columns)
            ->get()
            ->keyBy(fn (object $row): string => $this->key($row, $columns));
    }

    /**
     * @return array{views: int, share: float}
     */
    private function ofViews(int $views): array
    {
        return ['views' => $views, 'share' => round(min(1, $views / max(1, $this->views())), 3)];
    }

    private function views(): int
    {
        return $this->views ??= $this->events()->ofType('view')->count();
    }

    /**
     * One row per view and section with `ms` on screen. A null section is
     * time on the page.
     *
     * @return Collection<int, \stdClass>
     */
    private function dwell(): Collection
    {
        return $this->dwell ??= $this->events()->dwellPerView()->toBase()->get();
    }

    /**
     * @param  list<string>  $columns
     */
    private function key(object $row, array $columns): string
    {
        return implode("\0", array_map(fn (string $column): string => (string) data_get($row, $column), $columns));
    }

    private function query(): Query
    {
        return (new Footprint)->getConnection()->query();
    }
}
