<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
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
     * default, preserving backwards-compatible behavior).
     */
    public function resolve(Entry $entry, Carbon $from, ?Carbon $to = null, ?int $limit = null, bool $includeExcluded = false): Collection
    {
        $occurrences = collect();

        foreach ($this->dates($entry) as $dateRow) {
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

    public function representative(Entry $entry): ?Occurrence
    {
        $now = Carbon::now($this->timezone());
        $occurrences = $this->representativeCandidates($entry, $now);

        return $this->firstUpcoming($occurrences, $now)
            ?? $occurrences->sortBy(fn (Occurrence $o) => $o->start)->last();
    }

    public function nextUpcoming(Entry $entry, ?Carbon $from = null): ?Occurrence
    {
        $from ??= Carbon::now($this->timezone());

        return $this->firstUpcoming(
            $this->representativeCandidates($entry, $from),
            $from,
        );
    }

    public function findOccurrenceOnDate(Entry $entry, Carbon $date, bool $includeExcluded = false): ?Occurrence
    {
        $tz = $this->timezone();
        $startOfDay = $date->copy()->setTimezone($tz)->startOfDay();
        $endOfDay = $date->copy()->setTimezone($tz)->endOfDay();

        $occurrences = $this->resolve(
            entry: $entry,
            from: $startOfDay,
            to: $endOfDay,
            includeExcluded: $includeExcluded,
        );

        return $occurrences->first(function (Occurrence $o) use ($date, $tz) {
            return $o->start->isSameDay($date->copy()->setTimezone($tz));
        });
    }

    private function representativeCandidates(Entry $entry, Carbon $at): Collection
    {
        $occurrences = collect();

        foreach ($this->dates($entry) as $dateRow) {
            if (! is_array($dateRow)) {
                continue;
            }

            $occurrences = $occurrences->merge($this->resolveDateRow(
                $entry,
                $dateRow,
                Carbon::create(1, 1, 1, 0, 0, 0, $this->timezone()),
                null,
                null,
                includeExcluded: false,
                representativeAt: $at,
            ));
        }

        return $occurrences;
    }

    private function firstUpcoming(Collection $occurrences, Carbon $from): ?Occurrence
    {
        return $occurrences
            ->filter(fn (Occurrence $o) => $o->start->gte($from))
            ->sortBy(fn (Occurrence $o) => $o->start)
            ->first();
    }

    private function resolveDateRow(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, bool $includeExcluded = false, ?Carbon $representativeAt = null): Collection
    {
        $isRecurring = (bool) ($row['is_recurring'] ?? false);

        if (! $isRecurring) {
            return $this->resolveSingleDate($entry, $row, $from, $to);
        }

        return $this->resolveRecurringDate($entry, $row, $from, $to, $limit, $includeExcluded, $representativeAt);
    }

    private function resolveRecurringDate(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, bool $includeExcluded = false, ?Carbon $representativeAt = null): Collection
    {
        if (! $to && ! $limit && ! $representativeAt) {
            $to = $from->copy()->addYear();
        }

        $rruleParams = $this->buildRruleParams($row);

        if ($rruleParams === null) {
            return collect();
        }

        $tz = new DateTimeZone($this->timezone());

        // No EXDATEs: excluded dates stay in the iteration so they can be
        // emitted as first-class occurrences instead of silently vanishing.
        $rset = new RSet;
        $rset->addRRule($rruleParams);

        $exclusions = $this->parseExclusions($row, $tz);

        // Keyed by date only — the blueprint stores a bare replacement date,
        // so linking back to the occurrence that replaces it is date-level.
        $replacementsByDate = collect($exclusions)
            ->filter(fn (array $e) => $e['replacement_date'] !== null)
            ->keyBy(fn (array $e) => $e['replacement_date']->format('Y-m-d'));

        foreach (($row['additions'] ?? []) as $addition) {
            if (! is_array($addition) || empty($addition['date'])) {
                continue;
            }

            $day = $this->localDate($addition['date']);
            if (! $day) {
                continue;
            }

            $time = $this->localTime($addition['start_time'] ?? null) ?? '00:00';

            $rset->addDate(new DateTimeImmutable($day.' '.$time, $tz));
        }

        $occurrences = collect();
        $endTime = $row['end_time'] ?? null;
        $recurrenceDescription = $this->buildRecurrenceDescription($row);

        foreach ($rset as $date) {
            $start = Carbon::instance($date);

            $exclusion = $exclusions[$start->format('Y-m-d H:i:s')] ?? null;

            if ($exclusion && ! $includeExcluded) {
                continue;
            }

            if ($start->lt($from)) {
                continue;
            }

            if ($to && $start->gt($to)) {
                break;
            }

            $additionEndTime = $this->getAdditionEndTime($row['additions'] ?? [], $start);
            $effectiveEndTime = $additionEndTime ?? $endTime;

            $end = null;
            if ($effectiveEndTime) {
                $end = $start->copy()->setTimeFromTimeString((string) $effectiveEndTime);
            }

            $occurrence = new Occurrence(
                entry: $entry,
                start: $start,
                end: $end,
                isAllDay: (bool) ($row['is_all_day'] ?? false),
                isRecurring: true,
                recurrenceDescription: $recurrenceDescription,
                isExcluded: $exclusion !== null,
                replacementDate: $exclusion['replacement_date'] ?? null,
                replacesDate: $exclusion === null
                    ? ($replacementsByDate[$start->format('Y-m-d')]['datetime'] ?? null)
                    : null,
            );

            if ($representativeAt) {
                $occurrences = collect([$occurrence]);

                if ($start->gte($representativeAt)) {
                    break;
                }
            } else {
                $occurrences->push($occurrence);

                if ($limit && $occurrences->count() >= $limit) {
                    break;
                }
            }
        }

        return $occurrences;
    }

    /**
     * Exclusion rows keyed by their exact wall-clock datetime, matching how
     * RSet dates are formatted during iteration.
     *
     * @return array<string, array{datetime: Carbon, replacement_date: ?Carbon}>
     */
    private function parseExclusions(array $row, DateTimeZone $tz): array
    {
        $parsed = [];

        foreach (($row['exclusions'] ?? []) as $exclusion) {
            if (! is_array($exclusion) || empty($exclusion['date'])) {
                continue;
            }

            $day = $this->localDate($exclusion['date']);
            if (! $day) {
                continue;
            }

            $time = $this->localTime($exclusion['time'] ?? null)
                ?? $this->localTime($row['start_time'] ?? null)
                ?? '00:00';

            $datetime = Carbon::parse($day.' '.$time, $tz);
            $replacementDay = $this->localDate($exclusion['replacement_date'] ?? null);

            $parsed[$datetime->format('Y-m-d H:i:s')] = [
                'datetime' => $datetime,
                'replacement_date' => $replacementDay ? Carbon::parse($replacementDay, $tz) : null,
            ];
        }

        return $parsed;
    }

    private function getAdditionEndTime(array $additions, Carbon $date): ?string
    {
        foreach ($additions as $addition) {
            if (! is_array($addition) || empty($addition['date'])) {
                continue;
            }

            $day = $this->localDate($addition['date']);
            if (! $day) {
                continue;
            }

            $time = $this->localTime($addition['start_time'] ?? null) ?? '00:00';
            $additionDate = Carbon::parse($day.' '.$time, $this->timezone());

            if ($additionDate->equalTo($date)) {
                return $addition['end_time'] ?? null;
            }
        }

        return null;
    }

    private function buildRruleParams(array $row): ?array
    {
        $frequency = $row['frequency'] ?? null;
        $day = $this->localDate($row['start_date'] ?? null);

        if (! $frequency || ! $day) {
            return null;
        }

        $tz = new DateTimeZone($this->timezone());
        $startTime = $this->localTime($row['start_time'] ?? null) ?? '00:00';

        $params = [
            'FREQ' => $frequency,
            'INTERVAL' => $row['interval'] ?? 1,
            'DTSTART' => new DateTimeImmutable($day.' '.$startTime, $tz),
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
            $untilDay = $this->localDate($row['until']);
            if ($untilDay) {
                $params['UNTIL'] = new DateTimeImmutable($untilDay.' 23:59:59', $tz);
            }
        }

        return $params;
    }

    private function resolveSingleDate(Entry $entry, array $row, Carbon $from, ?Carbon $to): Collection
    {
        $start = $this->localDateTime($row['start_date'] ?? null, $row['start_time'] ?? null);
        $end = $this->localDateTime($row['end_date'] ?? null, $row['end_time'] ?? null);

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

    /**
     * The timezone events are authored and displayed in. Statamic 6 stores
     * date-field values as app-timezone instants; recurrence and wall-clock
     * times are computed in this timezone so DST is handled correctly. The
     * returned Carbons stay in this timezone, matching Statamic's
     * display-timezone contract (templates localize via modifiers).
     */
    private function timezone(): string
    {
        return (string) (
            config('statamic-calendar.timezone')
            ?: config('statamic.system.display_timezone')
            ?: config('app.timezone')
            ?: 'UTC'
        );
    }

    /**
     * Recover the wall-clock calendar day (Y-m-d) the editor picked. Statamic 6
     * stores date fields as the instant — in the app timezone — of midnight in
     * the editor's timezone, so we convert back to the event timezone before
     * reading the day. Tolerates both date-only and datetime stored values.
     */
    private function localDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value, config('app.timezone'))
            ->setTimezone($this->timezone())
            ->format('Y-m-d');
    }

    /**
     * Validate and return an "HH:MM" (optionally "HH:MM:SS") wall-clock time.
     * Anything blank or malformed returns null so callers fall back to a safe
     * default instead of letting a bad stored value crash the cache rebuild.
     */
    private function localTime(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if (! preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Build a wall-clock Carbon in the event timezone from a stored date value
     * and an optional "HH:MM" time string.
     */
    private function localDateTime(mixed $date, mixed $time): ?Carbon
    {
        if (! $day = $this->localDate($date)) {
            return null;
        }

        return Carbon::parse($day.' '.($this->localTime($time) ?? '00:00'), $this->timezone());
    }

    private function buildRecurrenceDescription(array $row): string
    {
        $rruleParams = $this->buildRruleParams($row);

        if ($rruleParams === null) {
            return '';
        }

        $rrule = new RRule($rruleParams);

        return $rrule->humanReadable([
            'locale' => (string) config('app.locale', 'en'),
            'include_start' => false,
            'include_until' => false,
        ]);
    }

    private function dates(Entry $entry): array
    {
        $field = $this->datesField();
        $dates = $entry->hasSupplement($field)
            ? $entry->getSupplement($field)
            : $entry->get($field);

        return is_array($dates) ? $dates : [];
    }

    private function datesField(): string
    {
        return (string) config('statamic-calendar.fields.dates', 'dates');
    }
}
