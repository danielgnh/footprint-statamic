<?php

namespace Danielgnh\FootprintStatamic\Tests;

use Danielgnh\Footprint\ServiceProvider as FootprintServiceProvider;
use Danielgnh\FootprintStatamic\ServiceProvider;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Http\Middleware\PingOutpost;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // A control panel request pings Statamic's licensing server.
        $this->withoutMiddleware(PingOutpost::class);
    }

    /**
     * Laravel 13 ships cache.serializable_classes => false, so every cached
     * answer has to survive unserialize() without its classes. Set before the
     * providers register, as Statamic adds its own classes to the list then.
     */
    #[\Override]
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('cache.serializable_classes', false);
        $app['config']->set('cache.stores.array.serialize', true);
    }

    #[\Override]
    protected function getPackageProviders($app)
    {
        // AddonTestCase skips package discovery, so Footprint is registered here.
        return [FootprintServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    #[\Override]
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $cipher = $app['config']->get('app.cipher', 'AES-256-CBC');
        $app['config']->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey($cipher)));
        $app['config']->set('app.url', 'https://site.test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('footprint.rate_limit', 10_000);

        // Solo allows one user, so a test with an editor who lacks the permission needs Pro.
        $app['config']->set('statamic.editions.pro', true);

        $fixtures = __DIR__.'/__fixtures__/dev-null';
        $app['config']->set('statamic.system.blueprints_path', $fixtures.'/blueprints');
        $app['config']->set('statamic.system.fieldsets_path', $fixtures.'/fieldsets');
        $app['config']->set('statamic.users.repositories.file.paths.roles', $fixtures.'/users/roles.yaml');
        $app['config']->set('statamic.users.repositories.file.paths.groups', $fixtures.'/users/groups.yaml');
    }
}
