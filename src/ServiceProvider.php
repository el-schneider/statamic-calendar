<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar;

use ElSchneider\StatamicCalendar\Console\Commands\RebuildOccurrenceCacheCommand;
use ElSchneider\StatamicCalendar\Http\Controllers\ApiOccurrenceController;
use ElSchneider\StatamicCalendar\Http\Controllers\IcsController;
use ElSchneider\StatamicCalendar\Listeners\RebuildOccurrenceCacheOnEntryChange;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use Illuminate\Support\Facades\Route;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        Tags\Calendar::class,
    ];

    protected $commands = [
        RebuildOccurrenceCacheCommand::class,
    ];

    protected $listen = [
        EntrySaved::class => [RebuildOccurrenceCacheOnEntryChange::class],
        EntryDeleted::class => [RebuildOccurrenceCacheOnEntryChange::class],
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/statamic-calendar.php', 'statamic-calendar');

        $this->app->singleton(OccurrenceCache::class);
    }

    public function bootAddon()
    {
        $this->app['view']->addLocation(__DIR__.'/../resources/views');

        $this->registerRoutes();
        $this->registerApiRoutes();

        $this->publishes([
            __DIR__.'/../config/statamic-calendar.php' => config_path('statamic-calendar.php'),
        ], 'statamic-calendar');

        $this->publishes([
            __DIR__.'/../resources/views/statamic-calendar' => resource_path('views/statamic-calendar'),
        ], 'statamic-calendar-views');

        $this->publishes([
            __DIR__.'/../resources/examples' => resource_path('vendor/statamic-calendar/examples'),
        ], 'statamic-calendar-examples');
    }

    protected function registerRoutes(): void
    {
        $index = config('statamic-calendar.routes.index');

        if ($index) {
            Route::statamic($index, 'statamic-calendar/index');
        }

        $this->registerIcsRoutes();
    }

    protected function registerApiRoutes(): void
    {
        if (! config('statamic-calendar.api.enabled', false)) {
            return;
        }

        $route = config('statamic-calendar.api.route', 'api/calendar/occurrences');
        $middleware = config('statamic-calendar.api.middleware', 'api');

        Route::middleware($middleware)
            ->get($route, [ApiOccurrenceController::class, 'index'])
            ->name('statamic-calendar.api.occurrences');
    }

    protected function registerIcsRoutes(): void
    {
        if (! config('statamic-calendar.ics.enabled', true)) {
            return;
        }

        $feedPath = config('statamic-calendar.ics.feed_url', '/calendar.ics');

        Route::get($feedPath, [IcsController::class, 'feed'])
            ->name('statamic-calendar.ics.feed');

        Route::get($feedPath.'/{occurrenceId}', [IcsController::class, 'download'])
            ->name('statamic-calendar.ics.download');
    }
}
