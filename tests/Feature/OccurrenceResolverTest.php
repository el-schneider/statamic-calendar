<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Entries\Entry;

/**
 * Builds an entry whose `dates` grid returns the given rows. The resolver only
 * reads `$entry->get('dates')`, so a mock is sufficient and keeps these tests
 * free of the Stache.
 */
function entryWithDates(array $rows): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('dates')->andReturn($rows);

    return $entry;
}

function resolveDates(array $rows, string $from = '2025-01-01', string $to = '2027-01-01'): Illuminate\Support\Collection
{
    return (new OccurrenceResolver)->resolve(
        entryWithDates($rows),
        Carbon::parse($from, 'UTC'),
        Carbon::parse($to, 'UTC'),
    );
}

beforeEach(function () {
    // Single-site setup: dates stored in UTC, authored/displayed in Berlin.
    config()->set('app.timezone', 'UTC');
    config()->set('statamic.system.display_timezone', 'Europe/Berlin');
    config()->set('statamic-calendar.timezone', null);
});

test('recurring row with a Statamic 6 datetime start_date does not crash (DTSTART regression)', function () {
    // v6 stores date-only fields as "Y-m-d H:i" — the addon used to concat this
    // with start_time, producing an invalid DTSTART that threw in php-rrule.
    $occurrences = resolveDates([[
        'start_date' => '2025-03-01 00:00',
        'start_time' => '08:00',
        'is_recurring' => true,
        'frequency' => 'DAILY',
        'interval' => 1,
        'recurrence_end' => 'count',
        'count' => 3,
    ]]);

    expect($occurrences)->toHaveCount(3);
    expect($occurrences->first()->start->format('Y-m-d H:i'))->toBe('2025-03-01 08:00');
    expect($occurrences->first()->start->getTimezone()->getName())->toBe('Europe/Berlin');
});

test('single date stored as a UTC instant resolves to the local calendar day (no off-by-one)', function () {
    // Editor in Berlin picks 2026-12-05; Statamic stores midnight-Berlin as the
    // UTC instant 2026-12-04 23:00. The occurrence must land on Dec 5, not Dec 4.
    $occurrences = resolveDates([[
        'start_date' => '2026-12-04 23:00',
        'start_time' => '09:00',
        'is_recurring' => false,
    ]]);

    expect($occurrences)->toHaveCount(1);
    expect($occurrences->first()->start->format('Y-m-d H:i'))->toBe('2026-12-05 09:00');
});

test('recurring occurrences keep their wall-clock time across a DST transition', function () {
    // EU springs forward on 2025-03-30. A daily 09:00 event must stay 09:00 local
    // before and after the switch (instant shifts, wall time does not).
    $occurrences = resolveDates([[
        'start_date' => '2025-03-28',
        'start_time' => '09:00',
        'is_recurring' => true,
        'frequency' => 'DAILY',
        'interval' => 1,
        'recurrence_end' => 'count',
        'count' => 5,
    ]]);

    $before = $occurrences->first(fn (Occurrence $o) => $o->start->format('Y-m-d') === '2025-03-28');
    $after = $occurrences->first(fn (Occurrence $o) => $o->start->format('Y-m-d') === '2025-03-31');

    expect($before->start->format('H:i'))->toBe('09:00');
    expect($before->start->format('P'))->toBe('+01:00');
    expect($after->start->format('H:i'))->toBe('09:00');
    expect($after->start->format('P'))->toBe('+02:00');
});

test('UNTIL is interpreted in the event timezone and is inclusive of the final day', function () {
    $occurrences = resolveDates([[
        'start_date' => '2025-06-01',
        'start_time' => '10:00',
        'is_recurring' => true,
        'frequency' => 'DAILY',
        'interval' => 1,
        'recurrence_end' => 'until',
        'until' => '2025-06-03',
    ]]);

    expect($occurrences->map(fn (Occurrence $o) => $o->start->format('Y-m-d'))->all())
        ->toBe(['2025-06-01', '2025-06-02', '2025-06-03']);
});

test('exclusions drop the matching local day', function () {
    $occurrences = resolveDates([[
        'start_date' => '2025-06-01',
        'start_time' => '10:00',
        'is_recurring' => true,
        'frequency' => 'DAILY',
        'interval' => 1,
        'recurrence_end' => 'count',
        'count' => 3,
        'exclusions' => [
            ['date' => '2025-06-02'],
        ],
    ]]);

    expect($occurrences->map(fn (Occurrence $o) => $o->start->format('Y-m-d'))->all())
        ->toBe(['2025-06-01', '2025-06-03']);
});

test('recurring row missing start_date yields nothing instead of crashing', function () {
    expect(resolveDates([[
        'is_recurring' => true,
        'frequency' => 'WEEKLY',
    ]]))->toHaveCount(0);
});

test('recurring row missing frequency yields nothing instead of crashing', function () {
    expect(resolveDates([[
        'start_date' => '2025-06-01',
        'start_time' => '10:00',
        'is_recurring' => true,
    ]]))->toHaveCount(0);
});

test('a malformed start_time falls back to midnight instead of crashing the rebuild', function () {
    $occurrences = resolveDates([[
        'start_date' => '2025-06-01',
        'start_time' => '25:99',
        'is_recurring' => true,
        'frequency' => 'DAILY',
        'interval' => 1,
        'recurrence_end' => 'count',
        'count' => 2,
    ]]);

    expect($occurrences)->toHaveCount(2);
    expect($occurrences->first()->start->format('Y-m-d H:i'))->toBe('2025-06-01 00:00');
});
