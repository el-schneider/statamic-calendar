<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Facades;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, OccurrenceData> all(bool $includeExcluded = false)
 * @method static Collection<int, OccurrenceData> on(Carbon $date, bool $includeExcluded = false)
 * @method static Collection<int, OccurrenceData> between(Carbon $from, Carbon $to, bool $includeExcluded = false)
 * @method static Collection<int, OccurrenceData> forEntry(string|int $entryId, bool $includeExcluded = false)
 * @method static Collection<int, OccurrenceData> forOrganizer(string|int|null $organizerId, bool $includeExcluded = false)
 * @method static Collection<int, OccurrenceData> upcoming(int $limit = 10, bool $includeExcluded = false)
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
