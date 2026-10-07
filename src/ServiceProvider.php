<?php

namespace Danielgnh\FootprintStatamic;

use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        Permission::extend(function () {
            Permission::register('view interactions')->label(__('View page interactions'));
        });
    }
}
