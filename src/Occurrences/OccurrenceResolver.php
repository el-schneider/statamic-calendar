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
    public function resolve(Entry $entry, Carbon $from, ?Carbon $to = null, ?int $limit = null): Collection
    {
        $occurrences = collect();

        foreach ($this->dates($entry) as $dateRow) {
            if (! is_array($dateRow)) {
                continue;
            }

            $rowOccurrences = $this->resolveDateRow($entry, $dateRow, $from, $to, $limit);
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
                representativeAt: $now,
            ));
        }

        $upcoming = $occurrences
            ->filter(fn (Occurrence $o) => $o->start->gte($now))
            ->sortBy(fn (Occurrence $o) => $o->start)
            ->first();

        return $upcoming ?? $occurrences->sortBy(fn (Occurrence $o) => $o->start)->last();
    }

    public function findOccurrenceOnDate(Entry $entry, Carbon $date): ?Occurrence
    {
        $tz = $this->timezone();
        $startOfDay = $date->copy()->setTimezone($tz)->startOfDay();
        $endOfDay = $date->copy()->setTimezone($tz)->endOfDay();

        $occurrences = $this->resolve(
            entry: $entry,
            from: $startOfDay,
            to: $endOfDay,
        );

        return $occurrences->first(function (Occurrence $o) use ($date, $tz) {
            return $o->start->isSameDay($date->copy()->setTimezone($tz));
        });
    }

    private function resolveDateRow(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, ?Carbon $representativeAt = null): Collection
    {
        $isRecurring = (bool) ($row['is_recurring'] ?? false);

        if (! $isRecurring) {
            return $this->resolveSingleDate($entry, $row, $from, $to);
        }

        return $this->resolveRecurringDate($entry, $row, $from, $to, $limit, $representativeAt);
    }

    private function resolveRecurringDate(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit, ?Carbon $representativeAt = null): Collection
    {
        if (! $to && ! $limit && ! $representativeAt) {
            $to = $from->copy()->addYear();
        }

        $rruleParams = $this->buildRruleParams($row);

        if ($rruleParams === null) {
            return collect();
        }

        $tz = new DateTimeZone($this->timezone());

        $rset = new RSet;
        $rset->addRRule($rruleParams);

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

            $rset->addExDate(new DateTimeImmutable($day.' '.$time, $tz));
        }

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
