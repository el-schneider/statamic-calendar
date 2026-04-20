<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Entries\Entry;

/**
 * These tests hit the resolver directly without booting Statamic. The Entry
 * is mocked with just the surface the resolver touches (`get(dates)`), and
 * the resolver produces plain Occurrence DTOs we can inspect.
 */

/**
 * @param  list<array<string, mixed>>  $dateRows
 */
function resolveFor(array $dateRows, Carbon $from, ?Carbon $to = null, bool $includeExcluded = false): Illuminate\Support\Collection
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('dates')->andReturn($dateRows);

    return (new OccurrenceResolver)->resolve($entry, $from, $to, null, $includeExcluded);
}

/**
 * Weekly-Monday recurring row starting 2026-03-02, 5 occurrences, with an
 * exclusion on 2026-03-16 moved to 2026-03-20 (Friday), plus that Friday as
 * an addition so the replacement actually materializes as an occurrence.
 */
function sampleRescheduledRow(array $overrides = []): array
{
    // Shallow replace so overriding `exclusions`/`additions` fully replaces the
    // sample array instead of merging element-by-element (which would leak
    // replacement_date from the defaults into a plain cancellation scenario).
    return array_replace([
        'is_recurring' => true,
        'start_date' => '2026-03-02',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'frequency' => 'WEEKLY',
        'interval' => 1,
        'weekdays' => ['MO'],
        'recurrence_end' => 'count',
        'count' => 5,
        'exclusions' => [
            ['date' => '2026-03-16', 'time' => '10:00', 'replacement_date' => '2026-03-20'],
        ],
        'additions' => [
            ['date' => '2026-03-20', 'start_time' => '10:00', 'end_time' => '11:00'],
        ],
    ], $overrides);
}

afterEach(fn () => Mockery::close());

test('excluded occurrences are hidden by default', function () {
    $occurrences = resolveFor([sampleRescheduledRow()], Carbon::parse('2026-03-01'));

    // RRULE COUNT=5 produces 5 Mondays; one is excluded; addition adds one.
    // Default (includeExcluded=false) → 5 visible.
    expect($occurrences)->toHaveCount(5);
    expect($occurrences->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->all())
        ->toBe(['2026-03-02', '2026-03-09', '2026-03-20', '2026-03-23', '2026-03-30']);
    expect($occurrences->every(fn (Occurrence $o) => ! $o->isExcluded))->toBeTrue();
});

test('include_excluded surfaces the excluded date with is_excluded=true', function () {
    $occurrences = resolveFor([sampleRescheduledRow()], Carbon::parse('2026-03-01'), includeExcluded: true);

    expect($occurrences)->toHaveCount(6);

    $excluded = $occurrences->first(fn (Occurrence $o) => $o->isExcluded);
    expect($excluded)->not->toBeNull();
    expect($excluded->start->format('Y-m-d'))->toBe('2026-03-16');
    expect($excluded->url())->toBe(''); // excluded occurrences have no destination
});

test('excluded occurrences expose replacement_date pointing to the new date', function () {
    $occurrences = resolveFor([sampleRescheduledRow()], Carbon::parse('2026-03-01'), includeExcluded: true);

    $excluded = $occurrences->first(fn (Occurrence $o) => $o->isExcluded);
    expect($excluded->replacementDate?->format('Y-m-d'))->toBe('2026-03-20');
});

test('replacement occurrences expose replaces_date pointing to the original', function () {
    $occurrences = resolveFor([sampleRescheduledRow()], Carbon::parse('2026-03-01'));

    $replacement = $occurrences->first(fn (Occurrence $o) => $o->start->format('Y-m-d') === '2026-03-20');
    expect($replacement)->not->toBeNull();
    expect($replacement->replacesDate?->format('Y-m-d'))->toBe('2026-03-16');
});

test('plain cancellation (no replacement_date) still marks is_excluded', function () {
    $row = sampleRescheduledRow([
        'exclusions' => [
            ['date' => '2026-03-16', 'time' => '10:00'],
        ],
        'additions' => [],
    ]);

    $occurrences = resolveFor([$row], Carbon::parse('2026-03-01'), includeExcluded: true);

    $excluded = $occurrences->first(fn (Occurrence $o) => $o->isExcluded);
    expect($excluded)->not->toBeNull();
    expect($excluded->replacementDate)->toBeNull();
});

test('exclusion without a time inherits the row start_time', function () {
    $row = sampleRescheduledRow([
        'exclusions' => [
            ['date' => '2026-03-16'], // no time → falls back to 10:00
        ],
        'additions' => [],
    ]);

    $occurrences = resolveFor([$row], Carbon::parse('2026-03-01'), includeExcluded: true);

    $excluded = $occurrences->first(fn (Occurrence $o) => $o->isExcluded);
    expect($excluded)->not->toBeNull();
});

test('occurrences outside the window are still filtered when excluded', function () {
    // Exclusion is before `from` — should not appear even with include_excluded.
    $row = sampleRescheduledRow();
    $occurrences = resolveFor([$row], Carbon::parse('2026-03-17'), includeExcluded: true);

    expect($occurrences->every(fn (Occurrence $o) => $o->start->gte(Carbon::parse('2026-03-17'))))->toBeTrue();
    expect($occurrences->contains(fn (Occurrence $o) => $o->start->format('Y-m-d') === '2026-03-16'))->toBeFalse();
});
