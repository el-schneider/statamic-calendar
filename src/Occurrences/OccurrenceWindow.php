<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Shared rules for deciding whether an event overlaps the requested dates
 * and whether it has finished. For example, a Monday–Friday exhibition must
 * appear in a Wednesday listing even though it started earlier.
 *
 * All-day events remain current through their final day. Events without an
 * end remain current through their start day. This is a listing rule only:
 * it does not add an end time to the event or its calendar download.
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
        $status = match (true) {
            $occurrence->start->gt($now) => 'upcoming',
            self::effectiveEnd($occurrence)->lt($now) => 'past',
            default => 'ongoing',
        };

        return in_array($status, $statuses, true);
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
