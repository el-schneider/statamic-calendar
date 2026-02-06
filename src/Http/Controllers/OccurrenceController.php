<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Http\Controllers;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Facades\Entry;
use Statamic\View\View;

class OccurrenceController
{
    public function __construct(
        private OccurrenceResolver $resolver
    ) {}

    public function show(int $year, int $month, int $day, string $slug)
    {
        $collection = (string) config('statamic-calendar.collection', 'events');

        $entry = Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->first();

        if (! $entry) {
            abort(404);
        }

        $date = Carbon::create($year, $month, $day);

        $cachedOccurrence = Occurrences::forEvent($entry->id())
            ->first(fn (OccurrenceData $o) => $o->start->isSameDay($date));

        if ($cachedOccurrence) {
            return $this->renderFromCache($entry, $cachedOccurrence);
        }

        $occurrence = $this->resolver->findOccurrenceOnDate($entry, $date);

        if (! $occurrence) {
            abort(404);
        }

        $data = $entry->toAugmentedArray();
        $data['start'] = $occurrence->start;
        $data['end'] = $occurrence->end;
        $data['is_all_day'] = $occurrence->isAllDay;
        $data['is_recurring'] = $occurrence->isRecurring;
        $data['recurrence_description'] = $occurrence->recurrenceDescription;
        $data['occurrence_url'] = $occurrence->url();

        return (new View)
            ->template($entry->template())
            ->layout($entry->layout())
            ->with($data);
    }

    private function renderFromCache($entry, OccurrenceData $occurrence)
    {
        $data = $entry->toAugmentedArray();
        $data['start'] = $occurrence->start;
        $data['end'] = $occurrence->end;
        $data['is_all_day'] = $occurrence->isAllDay;
        $data['is_recurring'] = $occurrence->isRecurring;
        $data['recurrence_description'] = $occurrence->recurrenceDescription;
        $data['occurrence_url'] = $occurrence->url;

        return (new View)
            ->template($entry->template())
            ->layout($entry->layout())
            ->with($data);
    }
}
