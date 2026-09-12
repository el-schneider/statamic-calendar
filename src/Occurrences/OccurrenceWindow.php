<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Interval semantics shared by cache, resolver, and presentation reads.
 *
 * An occurrence intersects a window when its effective end is on or after
 * the window start and its start is on or before the window end. All-day
 * occurrences remain active through the end of their final local day. Timed
 * occurrences without an explicit end also remain active through that day.
 */
class OccurrenceWindow
{
    public static function effectiveEnd(Occurrence|OccurrenceData $occurrence): Carbon
    {
        if (! $occurrence->end) {
            return $occurrence->start->copy()->endOfDay();
        }

        $end = $occurrence->end->copy();

        return $occurrence->isAllDay ? $end->endOfDay() : $end;
    }

    public static function matches(Occurrence|OccurrenceData $occurrence, ?Carbon $from = null, ?Carbon $to = null): bool
    {
        return (! $from || self::effectiveEnd($occurrence)->gte($from))
            && (! $to || $occurrence->start->lte($to));
    }

    public static function isOngoing(Occurrence|OccurrenceData $occurrence, Carbon $at): bool
    {
        return $occurrence->start->lte($at) && self::effectiveEnd($occurrence)->gte($at);
    }

    /**
     * @return array<string> Any of upcoming, ongoing, or past.
     *
     * @throws InvalidArgumentException When a supplied status is not supported.
     */
    public static function parseStatuses(mixed $statuses): array
    {
        if ($statuses === null || $statuses === '') {
            return [];
        }

        $statuses = is_string($statuses) ? explode(',', $statuses) : (is_array($statuses) ? $statuses : [$statuses]);
        $statuses = collect($statuses)
            ->map(fn ($status) => is_string($status) ? trim($status) : '')
            ->filter()
            ->values();

        $invalid = $statuses->reject(fn (string $status) => in_array($status, ['upcoming', 'ongoing', 'past'], true));
        if ($invalid->isNotEmpty()) {
            throw new InvalidArgumentException('Invalid occurrence status: '.$invalid->implode(', '));
        }

        return $statuses->all();
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
        return Carbon::now(self::timezone());
    }

    public static function timezone(): string
    {
        return (string) (
            config('statamic-calendar.timezone')
            ?: config('statamic.system.display_timezone')
            ?: config('app.timezone')
            ?: 'UTC'
        );
    }
}
