<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use RRule\RRule;
use RRule\RSet;
use Statamic\Entries\Entry;
use Throwable;

class OccurrenceResolver
{
    public function resolve(Entry $entry, Carbon $from, ?Carbon $to = null, ?int $limit = null): Collection
    {
        $dates = $entry->get($this->datesField()) ?? [];
        $occurrences = collect();

        foreach ($dates as $dateRow) {
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

    public function findOccurrenceOnDate(Entry $entry, Carbon $date): ?Occurrence
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $occurrences = $this->resolve(
            entry: $entry,
            from: $startOfDay,
            to: $endOfDay,
        );

        return $occurrences->first(function (Occurrence $o) use ($date) {
            return $o->start->isSameDay($date);
        });
    }

    private function resolveDateRow(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit): Collection
    {
        $isRecurring = (bool) ($row[$this->k('is_recurring')] ?? false);

        if (! $isRecurring) {
            return $this->resolveSingleDate($entry, $row, $from, $to);
        }

        return $this->resolveRecurringDate($entry, $row, $from, $to, $limit);
    }

    private function resolveRecurringDate(Entry $entry, array $row, Carbon $from, ?Carbon $to, ?int $limit): Collection
    {
        if (! $to && ! $limit) {
            $to = $from->copy()->addYear();
        }

        $rruleParams = $this->buildRruleParams($row);

        $rset = new RSet();
        $rset->addRRule($rruleParams);

        foreach (($row[$this->k('exclusions')] ?? []) as $exclusion) {
            if (! is_array($exclusion) || empty($exclusion['date'])) {
                continue;
            }

            $exdate = (string) $exclusion['date'];
            if (! empty($exclusion['time'])) {
                $exdate .= ' '.(string) $exclusion['time'];
            } else {
                $exdate .= ' '.(string) ($row[$this->k('start_time')] ?? '00:00');
            }

            $rset->addExDate($exdate);
        }

        foreach (($row[$this->k('additions')] ?? []) as $addition) {
            if (! is_array($addition) || empty($addition['date'])) {
                continue;
            }

            $rdate = (string) $addition['date'];
            if (! empty($addition['start_time'])) {
                $rdate .= ' '.(string) $addition['start_time'];
            }
            $rset->addDate($rdate);
        }

        $occurrences = collect();
        $endTime = $row[$this->k('end_time')] ?? null;
        $recurrenceDescription = $this->buildRecurrenceDescription($row);

        foreach ($rset as $date) {
            $start = Carbon::instance($date);

            if ($start->lt($from)) {
                continue;
            }

            if ($to && $start->gt($to)) {
                break;
            }

            $additionEndTime = $this->getAdditionEndTime($row[$this->k('additions')] ?? [], $start);
            $effectiveEndTime = $additionEndTime ?? $endTime;

            $end = null;
            if ($effectiveEndTime) {
                $end = $start->copy()->setTimeFromTimeString((string) $effectiveEndTime);
            }

            $occurrences->push(new Occurrence(
                event: $entry,
                start: $start,
                end: $end,
                isAllDay: (bool) ($row[$this->k('is_all_day')] ?? false),
                isRecurring: true,
                recurrenceDescription: $recurrenceDescription,
            ));

            if ($limit && $occurrences->count() >= $limit) {
                break;
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

            $additionDate = Carbon::parse((string) $addition['date']);
            if (! empty($addition['start_time'])) {
                $additionDate->setTimeFromTimeString((string) $addition['start_time']);
            }
            if ($additionDate->equalTo($date)) {
                return $addition['end_time'] ?? null;
            }
        }

        return null;
    }

    private function buildRruleParams(array $row): array
    {
        $startDate = (string) $row[$this->k('start_date')];
        $startTime = (string) ($row[$this->k('start_time')] ?? '00:00');
        $dtstart = $startDate.' '.$startTime;

        $params = [
            'FREQ' => $row[$this->k('frequency')],
            'INTERVAL' => $row[$this->k('interval')] ?? 1,
            'DTSTART' => $dtstart,
        ];

        if (($row[$this->k('frequency')] ?? null) === 'WEEKLY' && ! empty($row[$this->k('weekdays')])) {
            $params['BYDAY'] = $row[$this->k('weekdays')];
        }

        if (($row[$this->k('frequency')] ?? null) === 'MONTHLY' && ($row[$this->k('monthly_type')] ?? null) === 'weekday_position') {
            $ordinal = (string) ($row[$this->k('weekday_ordinal')] ?? '1');
            $weekday = (string) ($row[$this->k('weekday')] ?? 'MO');
            $params['BYDAY'] = $ordinal.$weekday;
        }

        if (($row[$this->k('frequency')] ?? null) === 'MONTHLY' && ($row[$this->k('monthly_type')] ?? null) === 'day_of_month') {
            $params['BYMONTHDAY'] = $row[$this->k('monthday')] ?? 1;
        }

        $recurrenceEnd = $row[$this->k('recurrence_end')] ?? 'never';
        if ($recurrenceEnd === 'count' && ! empty($row[$this->k('count')])) {
            $params['COUNT'] = $row[$this->k('count')];
        }
        if ($recurrenceEnd === 'until' && ! empty($row[$this->k('until')])) {
            $params['UNTIL'] = $row[$this->k('until')];
        }

        return $params;
    }

    private function resolveSingleDate(Entry $entry, array $row, Carbon $from, ?Carbon $to): Collection
    {
        $start = $this->parseDateTime($row[$this->k('start_date')] ?? null, $row[$this->k('start_time')] ?? null);
        $end = $this->parseDateTime($row[$this->k('end_date')] ?? null, $row[$this->k('end_time')] ?? null);

        if (! $start) {
            return collect();
        }

        if ($start->lt($from) || ($to && $start->gt($to))) {
            return collect();
        }

        return collect([
            new Occurrence(
                event: $entry,
                start: $start,
                end: $end,
                isAllDay: (bool) ($row[$this->k('is_all_day')] ?? false),
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
            'locale' => (string) $this->cfg('app.locale', 'en'),
            'include_start' => false,
            'include_until' => false,
        ]);
    }

    private function datesField(): string
    {
        return (string) $this->cfg('statamic-calendar.fields.dates.handle', 'dates');
    }

    private function k(string $key): string
    {
        return (string) $this->cfg('statamic-calendar.fields.dates.keys.'.$key, $key);
    }

    private function cfg(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
