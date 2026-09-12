<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;

/**
 * Interval semantics shared by cache, resolver, and presentation reads.
 *
 * An occurrence intersects a window when its effective end is on or after
 * the window start and its start is on or before the window end. All-day
 * occurrences remain active through the end of their final local day.
 */
class OccurrenceWindow
{
    public static function effectiveEnd(Occurrence|OccurrenceData $occurrence): Carbon
    {
        $end = ($occurrence->end ?? $occurrence->start)->copy();

        return $occurrence->isAllDay ? $end->endOfDay() : $end;
    }

    public static function matches(Occurrence|OccurrenceData $occurrence, Carbon $from, ?Carbon $to = null): bool
    {
        return self::effectiveEnd($occurrence)->gte($from)
            && (! $to || $occurrence->start->lte($to));
    }

    public static function isOngoing(Occurrence|OccurrenceData $occurrence, Carbon $at): bool
    {
        return $occurrence->start->lte($at) && self::effectiveEnd($occurrence)->gte($at);
    }

    /** @param array<string> $statuses */
    public static function hasStatus(Occurrence|OccurrenceData $occurrence, array $statuses, Carbon $now): bool
    {
        return collect($statuses)->contains(function (string $status) use ($occurrence, $now) {
            return match ($status) {
                'upcoming' => $occurrence->start->gt($now),
                'ongoing' => self::isOngoing($occurrence, $now),
                'past' => self::effectiveEnd($occurrence)->lt($now),
                default => false,
            };
        });
    }

    public static function now(): Carbon
    {
        return Carbon::now((string) (
            config('statamic-calendar.timezone')
            ?: config('statamic.system.display_timezone')
            ?: config('app.timezone')
            ?: 'UTC'
        ));
    }
}
