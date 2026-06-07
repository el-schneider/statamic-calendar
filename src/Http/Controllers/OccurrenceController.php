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

        if (! $entry || ! $entry->published()) {
            abort(404);
        }

        $date = Carbon::create($year, $month, $day);

        $cachedOccurrence = Occurrences::forEntry($entry->id())
            ->first(fn (OccurrenceData $o) => $o->start->isSameDay($date));

        if ($cachedOccurrence) {
            return $this->renderOccurrence($entry, [
                'start' => $cachedOccurrence->start,
                'end' => $cachedOccurrence->end,
                'is_all_day' => $cachedOccurrence->isAllDay,
                'is_recurring' => $cachedOccurrence->isRecurring,
                'recurrence_description' => $cachedOccurrence->recurrenceDescription,
                'occurrence_url' => $cachedOccurrence->url,
            ]);
        }

        $occurrence = $this->resolver->findOccurrenceOnDate($entry, $date);

        if (! $occurrence) {
            abort(404);
        }

        return $this->renderOccurrence($entry, [
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'occurrence_url' => $occurrence->url(),
        ]);
    }

    private function renderOccurrence($entry, array $occurrenceData)
    {
        $data = array_merge($entry->toAugmentedArray(), $occurrenceData);

        return (new View)
            ->template($entry->template())
            ->layout($entry->layout())
            ->with($data);
    }
}
