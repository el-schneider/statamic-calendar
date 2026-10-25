<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

beforeEach(function () {
    config()->set('statamic-calendar.timezone', 'Europe/Berlin');
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

test('upcoming includes occurrences in progress in the configured timezone', function () {
    Carbon::setTestNow('2026-06-12 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);
    Cache::forever('statamic_calendar.occurrences.v2', snapshot([
        cachedOccurrence('ongoing', '2026-06-12T10:00:00+02:00', '2026-06-12T13:00:00+02:00'),
        cachedOccurrence('future', '2026-06-12T14:00:00+02:00'),
        cachedOccurrence('past', '2026-06-12T08:00:00+02:00', '2026-06-12T09:00:00+02:00'),
    ], horizon: 730));

    expect(app(OccurrenceCache::class)->upcoming()->pluck('title')->all())
        ->toBe(['ongoing', 'future']);
});

test('a snapshot expanded under another max_days_ahead is not served', function () {
    Carbon::setTestNow('2026-10-25 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);
    Cache::forever('statamic_calendar.occurrences.v2', snapshot([
        cachedOccurrence('beyond-horizon', '2028-10-26T16:00:00+02:00'),
    ], horizon: 730));

    config()->set('statamic-calendar.url.max_days_ahead', 30);

    expect(app(OccurrenceCache::class)->isBuilt())->toBeFalse();
});

test('switching max_days_ahead away and back does not revive the earlier snapshot', function () {
    Carbon::setTestNow('2026-10-25 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);
    Cache::forever('statamic_calendar.occurrences.v2', snapshot([
        cachedOccurrence('revived', '2028-10-26T16:00:00+02:00'),
    ], horizon: 730));

    config()->set('statamic-calendar.url.max_days_ahead', 30);
    // A rebuild under 30 overwrites the single key, so the 730 snapshot is gone.
    Cache::forever('statamic_calendar.occurrences.v2', snapshot([], horizon: 30));

    config()->set('statamic-calendar.url.max_days_ahead', 730);

    expect(app(OccurrenceCache::class)->isBuilt())->toBeFalse();
});

test('a bare snapshot written before the horizon envelope is rebuilt, not served', function () {
    Carbon::setTestNow('2026-06-12 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);
    // Pre-envelope installs stored the occurrence list directly under the current key.
    Cache::forever('statamic_calendar.occurrences.v2', [
        cachedOccurrence('legacy', '2026-06-12T14:00:00+02:00'),
    ]);

    expect(app(OccurrenceCache::class)->isBuilt())->toBeFalse();
});

/**
 * @param  array<int, array<string, mixed>>  $occurrences
 */
function snapshot(array $occurrences, ?int $horizon): array
{
    return ['horizon' => $horizon, 'occurrences' => $occurrences];
}

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

test('a rebuild that is overwritten right after writing still serves its own occurrences', function () {
    Carbon::setTestNow('2026-06-12 12:00:00 Europe/Berlin');
    config()->set('statamic-calendar.url.max_days_ahead', 730);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('published')->andReturnTrue();
    $entry->shouldReceive('id')->andReturn('race-event');
    $entry->shouldReceive('slug')->andReturn('race-event');
    $entry->shouldReceive('urlWithoutRedirect')->andReturn('/events/race-event');
    $entry->shouldReceive('get')->with('dates')->andReturn([['start_date' => '2026-06-20']]);
    $entry->shouldReceive('get')->with('title')->andReturn('Race event');
    $entry->shouldReceive('get')->andReturn(null);

    $builder = Mockery::mock();
    $builder->shouldReceive('where')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([$entry]));
    EntryFacade::shouldReceive('query')->andReturn($builder);

    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('resolve')->andReturn(collect([
        new Occurrence($entry, Carbon::parse('2026-06-20 10:00'), null, false, false),
    ]));
    app()->instance(OccurrenceResolver::class, $resolver);

    // Simulates a concurrent writer: the first snapshot write is removed straight after it lands.
    $removed = false;
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$removed) {
        if (! $removed && $event->key === 'statamic_calendar.occurrences.v2') {
            $removed = true;
            Cache::forget($event->key);
        }
    });

    try {
        expect(app(OccurrenceCache::class)->all()->pluck('title')->all())->toBe(['Race event'])
            ->and($removed)->toBeTrue();
    } finally {
        Event::forget(KeyWritten::class);
    }
});
