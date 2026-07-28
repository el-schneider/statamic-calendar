<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Entries\CalendarEloquentEntry;
use ElSchneider\StatamicCalendar\Entries\CalendarEntry;
use ElSchneider\StatamicCalendar\ServiceProvider;
use Statamic\Providers\AddonServiceProvider;

it('allowlists the calendar entry classes for cache unserialization', function () {
    // `false` is the "restricted, nothing allowed yet" marker Statamic 6 apps ship.
    config()->set('cache.serializable_classes', false);

    (new ServiceProvider(app()))->register();

    expect(config('cache.serializable_classes'))
        ->toContain(CalendarEntry::class, CalendarEloquentEntry::class);
})->skip(
    ! method_exists(AddonServiceProvider::class, 'registerSerializableClasses'),
    'Statamic 5 has no cache class allowlist.'
);
