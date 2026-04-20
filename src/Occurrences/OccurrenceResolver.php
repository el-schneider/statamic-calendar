<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use RRule\RRule;
use RRule\RSet;
use Statamic\Entries\Entry;

class OccurrenceResolver
{
    /**
     * Resolve occurrences for an entry within an optional window.
     *
     * Exclusions are emitted as occurrences carrying `isExcluded: true` when
     * $includeExcluded is set; otherwise they are filtered out silently (the
     * default, preserving backwards-compatible behavior). The flag applies to
     * all strategies: recurring rows, single-date rows (a no-op since they
     * have no exclusions), and the cache rebuild (which always requests
     * excluded to keep a complete dataset).
     */
    public function resolve(Entry $entry, Carbon $from, ?Carbon $to = null, ?int $limit = null, bool $includeExcluded = false): Collection
    {
        $dates = $entry->get($this->datesField()) ?? [];
        $occurrences = collect();

        foreach ($dates as $dateRow) {
            if (! is_array($dateRow)) {
                continue;
            }

            $rowOccurrences = $this->resolveDateRow($entry, $dateRow, $from, $to, $limit, $includeExcluded);
            $occurrences = $occurrences->merge($rowOccurrences);
        }

        $occurrences = $occurrences->sortBy(fn (Occurrence $o) => $o->start);

        if ($limit) {
            $occurrences = $occurrences->take($limit);
        }

        return $occurrences->values();
    }

    public function findOccurrenceOnDate(Entry $entry, Carbon $date, bool $includeExcluded = false): ?Occurrence
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $occurrences = $this->resolve(
            entry: $entry,
            from: $startOfDay,
            to: $endOfDay,
            includeExcluded: $includeExcluded,
        );

        return $occurrences->first(function (Occurrence $o) use ($date) {
            return $o->start->isSameDay($date);
        });
    }

    private function resolveDateRow(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, bool $includeExcluded): Collection
    {
        $isRecurring = (bool) ($row['is_recurring'] ?? false);

        if (! $isRecurring) {
            return $this->resolveSingleDate($entry, $row, $from, $to);
        }

        return $this->resolveRecurringDate($entry, $row, $from, $to, $limit, $includeExcluded);
    }

    private function resolveRecurringDate(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, bool $includeExcluded): Collection
    {
        if (! $to && ! $limit) {
            $to = $from->copy()->addYear();
        }

        $rruleParams = $this->buildRruleParams($row);

        // Parse exclusions and additions into structured form once. The RSet
        // is built with RRULE + RDATEs only (no EXDATEs) so excluded dates
        // remain in the iteration and can be marked — this is the core shift
        // that turns exclusions into first-class data.
        $exclusions = $this->parseExclusions($row);
        $additions = $this->parseAdditions($row);

        // Keyed by exact occurrence datetime for O(1) lookup.
        $exclusionsByDatetime = collect($exclusions)->keyBy(
            fn (array $e) => $e['datetime']->format('Y-m-d H:i:s')
        );

        // Keyed by date (Y-m-d) so any occurrence landing on that date can
        // back-reference the exclusion it replaces. Replacement linking is
        // date-level because the blueprint field stores a bare date.
        $replacementsByDate = collect($exclusions)
            ->filter(fn (array $e) => $e['replacement_date'] !== null)
            ->keyBy(fn (array $e) => $e['replacement_date']->format('Y-m-d'));

        $rset = new RSet;
        $rset->addRRule($rruleParams);
        foreach ($additions as $addition) {
            $rset->addDate($addition['datetime']->format('Y-m-d H:i:s'));
        }

        $occurrences = collect();
        $endTime = $row['end_time'] ?? null;
        $recurrenceDescription = $this->buildRecurrenceDescription($row);

        foreach ($rset as $date) {
            $start = Carbon::instance($date);
            $datetimeKey = $start->format('Y-m-d H:i:s');
            $dateKey = $start->format('Y-m-d');

            $exclusion = $exclusionsByDatetime[$datetimeKey] ?? null;
            $isExcluded = $exclusion !== null;

            if ($isExcluded && ! $includeExcluded) {
                continue;
            }

            if ($start->lt($from)) {
                continue;
            }

            if ($to && $start->gt($to)) {
                break;
            }

            // Non-excluded occurrences that land on a date some exclusion
            // moved to get a back-reference pointing to the original date.
            $replacesDate = null;
            if (! $isExcluded && isset($replacementsByDate[$dateKey])) {
                $replacesDate = $replacementsByDate[$dateKey]['datetime'];
            }

            $additionEndTime = $this->getAdditionEndTime($additions, $start);
            $effectiveEndTime = $additionEndTime ?? $endTime;

            $end = null;
            if ($effectiveEndTime) {
                $end = $start->copy()->setTimeFromTimeString((string) $effectiveEndTime);
            }

            $occurrences->push(new Occurrence(
                entry: $entry,
                start: $start,
                end: $end,
                isAllDay: (bool) ($row['is_all_day'] ?? false),
                isRecurring: true,
                recurrenceDescription: $recurrenceDescription,
                isExcluded: $isExcluded,
                replacementDate: $exclusion['replacement_date'] ?? null,
                replacesDate: $replacesDate,
            ));

            if ($limit && $occurrences->count() >= $limit) {
                break;
            }
        }

        return $occurrences;
    }

    /**
     * @return list<array{datetime: Carbon, replacement_date: ?Carbon}>
     */
    private function parseExclusions(array $row): array
    {
        $fallbackTime = (string) ($row['start_time'] ?? '00:00');
        $parsed = [];

        foreach (($row['exclusions'] ?? []) as $exclusion) {
            if (! is_array($exclusion) || empty($exclusion['date'])) {
                continue;
            }

            $time = ! empty($exclusion['time']) ? (string) $exclusion['time'] : $fallbackTime;
            $datetime = Carbon::parse((string) $exclusion['date'].' '.$time);

            $replacement = null;
            if (! empty($exclusion['replacement_date'])) {
                $replacement = Carbon::parse((string) $exclusion['replacement_date'])->startOfDay();
            }

            $parsed[] = [
                'datetime' => $datetime,
                'replacement_date' => $replacement,
            ];
        }

        return $parsed;
    }

    /**
     * @return list<array{datetime: Carbon, end_time: ?string}>
     */
    private function parseAdditions(array $row): array
    {
        $parsed = [];

        foreach (($row['additions'] ?? []) as $addition) {
            if (! is_array($addition) || empty($addition['date'])) {
                continue;
            }

            $datetime = Carbon::parse((string) $addition['date']);
            if (! empty($addition['start_time'])) {
                $datetime->setTimeFromTimeString((string) $addition['start_time']);
            }

            $parsed[] = [
                'datetime' => $datetime,
                'end_time' => $addition['end_time'] ?? null,
            ];
        }

        return $parsed;
    }

    /**
     * @param  list<array{datetime: Carbon, end_time: ?string}>  $additions
     */
    private function getAdditionEndTime(array $additions, Carbon $date): ?string
    {
        foreach ($additions as $addition) {
            if ($addition['datetime']->equalTo($date)) {
                return $addition['end_time'];
            }
        }

        return null;
    }

    private function buildRruleParams(array $row): array
    {
        $frequency = $row['frequency'];
        $startTime = (string) ($row['start_time'] ?? '00:00');

        $params = [
            'FREQ' => $frequency,
            'INTERVAL' => $row['interval'] ?? 1,
            'DTSTART' => $row['start_date'].' '.$startTime,
        ];

        if ($frequency === 'WEEKLY' && ! empty($row['weekdays'])) {
            $params['BYDAY'] = $row['weekdays'];
        }

        if ($frequency === 'MONTHLY') {
            $monthlyType = $row['monthly_type'] ?? null;

            if ($monthlyType === 'weekday_position') {
                $params['BYDAY'] = ($row['weekday_ordinal'] ?? '1').($row['weekday'] ?? 'MO');
            } elseif ($monthlyType === 'day_of_month') {
                $params['BYMONTHDAY'] = $row['monthday'] ?? 1;
            }
        }

        $recurrenceEnd = $row['recurrence_end'] ?? 'never';
        if ($recurrenceEnd === 'count' && ! empty($row['count'])) {
            $params['COUNT'] = $row['count'];
        }
        if ($recurrenceEnd === 'until' && ! empty($row['until'])) {
            $params['UNTIL'] = $row['until'];
        }

        return $params;
    }

    private function resolveSingleDate(Entry $entry, array $row, Carbon $from, ?Carbon $to): Collection
    {
        $start = $this->parseDateTime($row['start_date'] ?? null, $row['start_time'] ?? null);
        $end = $this->parseDateTime($row['end_date'] ?? null, $row['end_time'] ?? null);

        if (! $start) {
            return collect();
        }

        if ($start->lt($from) || ($to && $start->gt($to))) {
            return collect();
        }

        return collect([
            new Occurrence(
                entry: $entry,
                start: $start,
                end: $end,
                isAllDay: (bool) ($row['is_all_day'] ?? false),
                isRecurring: false,
            ),
        ]);
    }

    private function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $datetime = Carbon::parse($date);

        if ($time) {
            $datetime->setTimeFromTimeString($time);
        }

        return $datetime;
    }

    private function buildRecurrenceDescription(array $row): string
    {
        $rruleParams = $this->buildRruleParams($row);
        $rrule = new RRule($rruleParams);

        return $rrule->humanReadable([
            'locale' => (string) config('app.locale', 'en'),
            'include_start' => false,
            'include_until' => false,
        ]);
    }

    private function datesField(): string
    {
        return (string) config('statamic-calendar.fields.dates', 'dates');
    }
}
