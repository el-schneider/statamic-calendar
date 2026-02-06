<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Facades;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, OccurrenceData> all()
 * @method static Collection<int, OccurrenceData> on(Carbon $date)
 * @method static Collection<int, OccurrenceData> between(Carbon $from, Carbon $to)
 * @method static Collection<int, OccurrenceData> forEvent(string $eventId)
 * @method static Collection<int, OccurrenceData> forOrganizer(?string $organizerId)
 * @method static Collection<int, OccurrenceData> upcoming(int $limit = 10)
 * @method static void rebuild()
 * @method static void clear()
 * @method static bool isBuilt()
 *
 * @see OccurrenceCache
 */
class Occurrences extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OccurrenceCache::class;
    }
}
