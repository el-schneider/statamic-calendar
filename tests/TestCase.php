<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

use ElSchneider\StatamicCalendar\ServiceProvider;
use Facades\Statamic\Version;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ReflectionClass;
use Statamic\Console\Processes\Composer;
use Statamic\Extend\Manifest;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;

abstract class TestCase extends OrchestraTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMix();
        $this->withoutVite();

        // Statamic's AddonTestCase uses addToAssertionCount(-1/-2) to
        // suppress Mockery counts, which PHPUnit 12 forbids (final method,
        // asserts $count >= 0). We replicate the mocking without that hack.
        Version::shouldReceive('get')->zeroOrMoreTimes()
            ->andReturn(Composer::create(__DIR__.'/../vendor/statamic/cms/')->installedVersion(Statamic::PACKAGE));

        \Statamic\Facades\CP\Nav::shouldReceive('build')->zeroOrMoreTimes()->andReturn(collect());
        \Statamic\Facades\CP\Nav::shouldReceive('clearCachedUrls')->zeroOrMoreTimes();
    }

    protected function getPackageProviders($app): array
    {
        return [
            StatamicServiceProvider::class,
            $this->addonServiceProvider,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return ['Statamic' => Statamic::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $reflector = new ReflectionClass($this->addonServiceProvider);
        $directory = dirname($reflector->getFileName());

        $providerParts = explode('\\', $this->addonServiceProvider, -1);
        $namespace = implode('\\', $providerParts);

        $json = json_decode($app['files']->get($directory.'/../composer.json'), true);
        $statamic = $json['extra']['statamic'] ?? [];
        $autoload = $json['autoload']['psr-4'][$namespace.'\\'];

        $app->make(Manifest::class)->manifest = [
            $json['name'] => [
                'id' => $json['name'],
                'slug' => $statamic['slug'] ?? null,
                'version' => 'dev-main',
                'namespace' => $namespace,
                'autoload' => $autoload,
                'provider' => $this->addonServiceProvider,
            ],
        ];

        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.watcher', false);
        $app['config']->set('statamic.stache.stores.taxonomies.directory', $directory.'/../tests/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.terms.directory', $directory.'/../tests/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.collections.directory', $directory.'/../tests/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.entries.directory', $directory.'/../tests/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.navigation.directory', $directory.'/../tests/__fixtures__/content/navigation');
        $app['config']->set('statamic.stache.stores.globals.directory', $directory.'/../tests/__fixtures__/content/globals');
        $app['config']->set('statamic.stache.stores.global-variables.directory', $directory.'/../tests/__fixtures__/content/globals');
        $app['config']->set('statamic.stache.stores.asset-containers.directory', $directory.'/../tests/__fixtures__/content/assets');
        $app['config']->set('statamic.stache.stores.nav-trees.directory', $directory.'/../tests/__fixtures__/content/structures/navigation');
        $app['config']->set('statamic.stache.stores.collection-trees.directory', $directory.'/../tests/__fixtures__/content/structures/collections');
        $app['config']->set('statamic.stache.stores.form-submissions.directory', $directory.'/../tests/__fixtures__/content/submissions');
        $app['config']->set('statamic.stache.stores.users.directory', $directory.'/../tests/__fixtures__/users');

        $app['config']->set('statamic-calendar.api.enabled', true);
    }
}
