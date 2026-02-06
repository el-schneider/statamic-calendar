<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar;

use ElSchneider\StatamicCalendar\Console\Commands\RebuildOccurrenceCacheCommand;
use ElSchneider\StatamicCalendar\Listeners\RebuildOccurrenceCacheOnEntryChange;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use Illuminate\Support\Facades\Event;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        Tags\Events::class,
    ];

    protected $commands = [
        RebuildOccurrenceCacheCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/statamic-calendar.php', 'statamic-calendar');

        $this->app->singleton(OccurrenceCache::class);
    }

    public function bootAddon()
    {
        if (config('statamic-calendar.url.strategy') === 'date_segments') {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->app['view']->addLocation(__DIR__.'/../resources/views');

        $this->publishes([
            __DIR__.'/../config/statamic-calendar.php' => config_path('statamic-calendar.php'),
        ], 'statamic-calendar');

        $this->publishes([
            __DIR__.'/../resources/views/statamic-calendar' => resource_path('views/statamic-calendar'),
        ], 'statamic-calendar-views');

        $this->publishes([
            __DIR__.'/../resources/examples' => resource_path('vendor/statamic-calendar/examples'),
        ], 'statamic-calendar-examples');

        Event::listen(EntrySaved::class, RebuildOccurrenceCacheOnEntryChange::class);
        Event::listen(EntryDeleted::class, RebuildOccurrenceCacheOnEntryChange::class);
    }
}
