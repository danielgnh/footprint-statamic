<?php

namespace Danielgnh\FootprintStatamic;

use Closure;
use Danielgnh\Footprint\Ingest\PathNormalizer;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

/**
 * The page an entry is served at, as Footprint recorded it.
 */
final class Page
{
    private static ?Closure $urlResolver = null;

    public function __construct(
        public readonly string $url,
        public readonly ?string $host = null,
    ) {}

    public static function of(Entry $entry): ?self
    {
        $url = self::$urlResolver instanceof Closure ? (self::$urlResolver)($entry) : $entry->url();

        if (blank($url)) {
            return null;
        }

        return new self(URL::makeRelative($url), self::ownHost($entry));
    }

    /**
     * Maps an entry to the URL visitors load, for a front end that serves
     * entries somewhere other than $entry->url().
     *
     * @param  (Closure(Entry): ?string)|null  $resolver
     */
    public static function urlUsing(?Closure $resolver): void
    {
        self::$urlResolver = $resolver;
    }

    public function path(): string
    {
        return (string) app(PathNormalizer::class)->normalize($this->url);
    }

    /**
     * What the queries match: the path, plus the host on a multisite whose
     * sites have their own domains, so their rows stay apart.
     */
    public function query(): string
    {
        return $this->host === null ? $this->path() : "//{$this->host}{$this->path()}";
    }

    /**
     * Where the tab's frame loads the page. Site-relative keeps it on the
     * control panel's origin whatever APP_URL says, so the overlay can read
     * it. A page on another domain loads, but cannot be drawn over.
     */
    public function frame(): string
    {
        return $this->host === null ? $this->url : "//{$this->host}{$this->url}";
    }

    private static function ownHost(Entry $entry): ?string
    {
        $hosts = Site::all()->map(fn ($site): ?string => self::hostOf($site->absoluteUrl()))->unique();

        return $hosts->count() > 1 ? self::hostOf($entry->site()->absoluteUrl()) : null;
    }

    private static function hostOf(mixed $url): ?string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }
}
