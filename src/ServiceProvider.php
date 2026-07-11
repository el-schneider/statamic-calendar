<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar;

use ElSchneider\StatamicCalendar\Console\Commands\RebuildOccurrenceCacheCommand;
use ElSchneider\StatamicCalendar\Entries\CalendarEloquentEntry;
use ElSchneider\StatamicCalendar\Entries\CalendarEntry;
use ElSchneider\StatamicCalendar\Http\Controllers\ApiOccurrenceController;
use ElSchneider\StatamicCalendar\Http\Controllers\IcsController;
use ElSchneider\StatamicCalendar\Listeners\RebuildOccurrenceCacheOnEntryChange;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Events\CollectionSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Collection;
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
        $this->registerCalendarEntryClass();
        Event::listen(CollectionSaved::class, function (CollectionSaved $event): void {
            $this->configureCollectionEntryClass($event->collection);
        });

        $this->app['view']->addLocation(__DIR__.'/../resources/views');
        $this->app['view']->composer('statamic::entries.edit', function ($view): void {
            $entry = $view->getData()['entry'] ?? null;

            if ($entry instanceof EntryContract && $entry->collectionHandle() === config('statamic-calendar.collection', 'events')) {
                $view->with('collectionHasRoutes', true);
            }
        });

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

    private function registerCalendarEntryClass(): void
    {
        $collection = Collection::find(config('statamic-calendar.collection', 'events'));

        if ($collection && method_exists($collection, 'entryClass')) {
            $this->configureCollectionEntryClass($collection);

            return;
        }

        $stockClass = $this->usesEloquentEntries()
            ? \Statamic\Eloquent\Entries\Entry::class
            : \Statamic\Entries\Entry::class;

        if (get_class(app(EntryContract::class)) !== $stockClass) {
            return;
        }

        $entryClass = $this->calendarEntryClass();
        $this->app->bind(EntryContract::class, $entryClass);

        if ($this->usesEloquentEntries()) {
            $this->app->bind('statamic.eloquent.entries.entry', fn () => $entryClass);
        }
    }

    private function configureCollectionEntryClass($collection): void
    {
        if ($collection->handle() !== config('statamic-calendar.collection', 'events')
            || ! method_exists($collection, 'entryClass')
            || $collection->entryClass()) {
            return;
        }

        $collection->entryClass($this->calendarEntryClass());
    }

    private function calendarEntryClass(): string
    {
        return $this->usesEloquentEntries()
            ? CalendarEloquentEntry::class
            : CalendarEntry::class;
    }

    private function usesEloquentEntries(): bool
    {
        return class_exists(\Statamic\Eloquent\Entries\Entry::class)
            && config('statamic.eloquent-driver.entries.driver') === 'eloquent';
    }
}
