<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('statamic-calendar.timezone', 'Europe/Berlin');
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

test('upcoming includes occurrences in progress in the configured timezone', function () {
    Carbon::setTestNow('2026-06-12 12:00:00 Europe/Berlin');
    Cache::forever('statamic_calendar.occurrences.v2.h730', [
        cachedOccurrence('ongoing', '2026-06-12T10:00:00+02:00', '2026-06-12T13:00:00+02:00'),
        cachedOccurrence('future', '2026-06-12T14:00:00+02:00'),
        cachedOccurrence('past', '2026-06-12T08:00:00+02:00', '2026-06-12T09:00:00+02:00'),
    ]);

    expect(app(OccurrenceCache::class)->upcoming()->pluck('title')->all())
        ->toBe(['ongoing', 'future']);
});

test('a snapshot expanded under a larger max_days_ahead is not served after it shrinks', function () {
    Carbon::setTestNow('2026-10-25 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);

    // Seed the snapshot under the keys an older or unfixed build would read.
    $stale = [cachedOccurrence('beyond-horizon', '2028-10-26T16:00:00+02:00')];
    Cache::forever('statamic_calendar.occurrences.v2', $stale);
    Cache::forever('statamic_calendar.occurrences.v2.h730', $stale);

    config()->set('statamic-calendar.url.max_days_ahead', 30);

    expect(app(OccurrenceCache::class)->all()->pluck('title')->all())
        ->not->toContain('beyond-horizon');
});

test('caches written in an older payload format are not served', function () {
    Cache::forever('statamic_calendar.occurrences', [
        cachedOccurrence('stale', '2026-06-12T14:00:00+02:00'),
    ]);

    expect(app(OccurrenceCache::class)->all())->toBeEmpty();
});

function cachedOccurrence(string $title, string $start, ?string $end = null): array
{
    return [
        'id' => $title,
        'entry_id' => $title,
        'title' => $title,
        'slug' => $title,
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => $start,
        'end' => $end,
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '',
    ];
}
