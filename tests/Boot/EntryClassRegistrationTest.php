<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Entries\CalendarEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

beforeEach(function () {
    config()->set('statamic-calendar.url.strategy', 'date_segments');
});

test('entries get calendar urls when the collection already existed at boot', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    // Real requests rebuild collections from the persisted Stache, so anything the
    // addon set on the boot-time instance is gone by the time entries are made.
    Stache::clear();

    $entry = Entry::make()
        ->collection(Collection::find('events'))
        ->locale('default')
        ->slug('community-meetup')
        ->data(['dates' => [['start_date' => '2026-07-12', 'start_time' => '18:00']]]);

    expect($entry)->toBeInstanceOf(CalendarEntry::class)
        ->and($entry->url())->toBe('/calendar/2026/07/12/community-meetup');
});
