<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Http\Controllers;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\CP\LivePreview;
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

        $previewEntry = $this->previewEntry($collection, $slug);
        $entry = $previewEntry ?? Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->first();

        if (! $entry || (! $previewEntry && ! $entry->published())) {
            abort(404);
        }

        $date = Carbon::create($year, $month, $day);

        $cachedOccurrence = $previewEntry
            ? null
            : Occurrences::forEntry($entry->id())
                ->first(fn (OccurrenceData $o) => $o->start->isSameDay($date));

        if ($cachedOccurrence) {
            $occurrenceData = [
                'start' => $cachedOccurrence->start,
                'end' => $cachedOccurrence->end,
                'is_all_day' => $cachedOccurrence->isAllDay,
                'is_recurring' => $cachedOccurrence->isRecurring,
                'recurrence_description' => $cachedOccurrence->recurrenceDescription,
                'occurrence_url' => $cachedOccurrence->url,
            ];

            if ($redirect = $this->redirectExpiredOccurrence($entry, $cachedOccurrence->start)) {
                return $redirect;
            }

            return $this->renderOccurrence($entry, $occurrenceData);
        }

        $occurrence = $this->resolver->findOccurrenceOnDate($entry, $date);

        if (! $occurrence) {
            abort(404);
        }

        if ($redirect = $this->redirectExpiredOccurrence($entry, $occurrence->start)) {
            return $redirect;
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

    private function previewEntry(string $collection, string $slug): ?EntryContract
    {
        if (! request()->isLivePreview()) {
            return null;
        }

        $entry = app(LivePreview::class)->item(request()->statamicToken());

        if (! $entry instanceof EntryContract
            || $entry->collectionHandle() !== $collection
            || $entry->slug() !== $slug) {
            return null;
        }

        return $entry;
    }

    private function redirectExpiredOccurrence(EntryContract $entry, Carbon $occurrenceStart)
    {
        if (! config('statamic-calendar.url.redirect_expired', true)) {
            return null;
        }

        if (request()->isLivePreview()) {
            return null;
        }

        if ($occurrenceStart->copy()->endOfDay()->gte(Carbon::now($occurrenceStart->getTimezone()))) {
            return null;
        }

        $next = $this->resolver->nextUpcoming($entry);

        if (! $next) {
            return null;
        }

        return redirect()->to($this->absoluteOccurrenceUrl($entry, $next->url()), 301);
    }

    private function renderOccurrence($entry, array $occurrenceData)
    {
        $occurrenceData['occurrence_canonical_url'] = $this->absoluteOccurrenceUrl(
            $entry,
            (string) $occurrenceData['occurrence_url'],
        );

        $data = array_merge($entry->toAugmentedArray(), $occurrenceData);

        return (new View)
            ->template($entry->template())
            ->layout($entry->layout())
            ->with($data);
    }

    private function absoluteOccurrenceUrl(EntryContract $entry, string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($entry->site()->absoluteUrl(), '/').'/'.ltrim($url, '/');
    }
}
