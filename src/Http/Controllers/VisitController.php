<?php

namespace Danielgnh\FootprintStatamic\Http\Controllers;

use Danielgnh\FootprintStatamic\PageInteractions;
use Illuminate\Support\Facades\Cache;
use Statamic\Http\Controllers\CP\CpController;

class VisitController extends CpController
{
    /**
     * @return array{data: array{visit: array<string, mixed>, events: array<int, array<string, mixed>>}}
     */
    public function show(string $visit): array
    {
        $this->authorize('view interactions');

        return Cache::remember("footprint-statamic:visit:{$visit}", (int) config('footprint-statamic.cache'), fn (): array => [
            'data' => PageInteractions::visit($visit),
        ]);
    }
}
