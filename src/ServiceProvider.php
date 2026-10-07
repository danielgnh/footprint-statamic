<?php

namespace Danielgnh\FootprintStatamic;

use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        Permission::extend(function () {
            Permission::register('view interactions')->label(__('View page interactions'));
        });
    }
}
