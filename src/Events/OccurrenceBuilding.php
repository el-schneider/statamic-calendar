<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Events;

use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use Statamic\Contracts\Entries\Entry;

/**
 * Fired during occurrence cache rebuild, once per materialized occurrence,
 * before the payload is stored. Listeners can add `$extra` fields that end up
 * on `OccurrenceData` and in API / tag output.
 *
 * Runs at cache build time only — zero cost on reads. Results are frozen
 * until the next rebuild; depend on entry and occurrence data, not request context.
 *
 * Extra values must stay JSON-serializable (scalars, arrays, Arrayable).
 * Core occurrence fields always win on name collisions.
 *
 * Example:
 *
 *     use ElSchneider\StatamicCalendar\Events\OccurrenceBuilding;
 *     use Illuminate\Support\Facades\Event;
 *
 *     Event::listen(OccurrenceBuilding::class, function (OccurrenceBuilding $e) {
 *         $e->extra['image'] = $e->entry->augmentedValue('image')->shallow()->value();
 *         $e->extra['category'] = $e->entry->get('category');
 *         $e->extra['occurrence_date'] = $e->occurrence->start->toDateString();
 *     });
 */
final class OccurrenceBuilding
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly Entry $entry,
        public readonly Occurrence $occurrence,
        public array $extra = [],
    ) {}
}
