<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tags;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Tags\Tags;

class Events extends Tags
{
    protected static $handle = 'events';

    public function __construct(
        protected OccurrenceResolver $resolver
    ) {}

    public function index(): array
    {
        $collection = (string) $this->params->get('collection', config('statamic-calendar.collection', 'events'));
        $from = Carbon::parse((string) $this->params->get('from', 'now'));
        $to = $this->params->has('to') ? Carbon::parse((string) $this->params->get('to')) : null;
        $limit = $this->params->int('limit');

        $tags = $this->params->get('tags') ?? $this->params->get('event_tags');

        if ($collection === config('statamic-calendar.collection', 'events')) {
            return $this->indexFromCache($from, $to, $limit, $tags);
        }

        return $this->indexFromResolver($collection, $from, $to, $limit, $tags);
    }

    /**
     * Resolves the current occurrence for an entry based on a date query param.
     *
     * Usage: {{ events:current_occurrence }} ... {{ /events:current_occurrence }}
     */
    public function currentOccurrence(): mixed
    {
        $entryId = $this->context->get('id');

        if ($entryId instanceof \Statamic\Fields\Value) {
            $entryId = $entryId->value();
        }

        $entry = is_string($entryId) ? Entry::find($entryId) : null;

        if (! $entry) {
            return '';
        }

        $occurrence = $this->resolveCurrentOccurrence($entry);

        if (! $occurrence) {
            return '';
        }

        return $this->parse([
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'occurrence_url' => $occurrence->url(),
        ]);
    }

    private function resolveCurrentOccurrence($entry): ?Occurrence
    {
        $param = (string) config('statamic-calendar.url.query_string.param', 'date');
        $dateString = request()->query($param);

        if ($dateString) {
            $date = Carbon::parse((string) $dateString);

            return $this->resolver->findOccurrenceOnDate($entry, $date);
        }

        // No date param — resolve the next upcoming occurrence, or the most recent past one.
        $now = Carbon::now();
        $upcoming = $this->resolver->resolve($entry, $now, limit: 1);

        if ($upcoming->isNotEmpty()) {
            return $upcoming->first();
        }

        // No future occurrences — find the most recent past one.
        $earliest = $this->findEarliestStart($entry);

        if (! $earliest) {
            return null;
        }

        return $this->resolver->resolve($entry, $earliest, $now)
            ->last();
    }

    private function findEarliestStart($entry): ?Carbon
    {
        $dates = $entry->get((string) config('statamic-calendar.fields.dates.handle', 'dates')) ?? [];
        $startKey = (string) config('statamic-calendar.fields.dates.keys.start_date', 'start_date');

        return collect($dates)
            ->filter(fn ($d) => is_array($d) && ! empty($d[$startKey]))
            ->map(fn ($d) => Carbon::parse((string) $d[$startKey]))
            ->sort()
            ->first();
    }

    /**
     * Usage: {{ events:for_organizer :organizer="id" limit="5" }}
     */
    public function forOrganizer(): array
    {
        $organizerId = $this->params->get('organizer') ?? $this->context->get('id');
        $limit = $this->params->int('limit', 5);
        $from = Carbon::now();

        return Occurrences::forOrganizer(is_string($organizerId) ? $organizerId : null)
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->sortBy(fn (OccurrenceData $o) => $o->start)
            ->take($limit)
            ->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))
            ->values()
            ->all();
    }

    /**
     * Backwards compatible alias.
     * Usage: {{ events:for_member :member="id" limit="5" }}
     */
    public function forMember(): array
    {
        $memberId = $this->params->get('member') ?? $this->context->get('id');

        return $this->forOrganizerWithId($memberId);
    }

    public function nextOccurrences(): array
    {
        $entryId = $this->params->get('entry') ?? $this->context->get('id');
        $entry = is_string($entryId) ? Entry::find($entryId) : null;

        if (! $entry) {
            return [];
        }

        $contextStart = $this->getContextStart();

        $from = $this->params->get('from');
        $from = $from ? Carbon::parse((string) $from) : ($contextStart ?? Carbon::now());

        $to = $this->params->has('to') ? Carbon::parse((string) $this->params->get('to')) : null;
        $limit = $this->params->int('limit', 5);

        $occurrences = $this->resolver->resolve($entry, $from, $to, $limit);

        if ($contextStart && ! $this->params->bool('include_current', false)) {
            $occurrences = $occurrences->reject(fn (Occurrence $o) => $o->start->equalTo($contextStart));
        }

        return $occurrences->map(fn (Occurrence $o) => $this->occurrenceToArray($o))->values()->all();
    }

    private function forOrganizerWithId($id): array
    {
        $organizerId = is_string($id) ? $id : null;
        $limit = $this->params->int('limit', 5);
        $from = Carbon::now();

        return Occurrences::forOrganizer($organizerId)
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->sortBy(fn (OccurrenceData $o) => $o->start)
            ->take($limit)
            ->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))
            ->values()
            ->all();
    }

    private function indexFromCache(Carbon $from, ?Carbon $to, ?int $limit, $tags): array
    {
        $occurrences = Occurrences::all()
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->when($to, fn ($c) => $c->filter(fn (OccurrenceData $o) => $o->start->lte($to)));

        if ($tags) {
            $tagSlugs = $this->normalizeTagSlugs($tags);
            if ($tagSlugs) {
                $occurrences = $occurrences->filter(fn (OccurrenceData $o) => $o->hasAnyTag($tagSlugs));
            }
        }

        $occurrences = $occurrences->sortBy(fn (OccurrenceData $o) => $o->start);

        if ($limit) {
            $occurrences = $occurrences->take($limit);
        }

        return $occurrences->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))->values()->all();
    }

    /**
     * @return array<string>
     */
    private function normalizeTagSlugs($tags): array
    {
        if ($tags instanceof TermQueryBuilder) {
            $tags = $tags->get();
        }

        if (is_string($tags)) {
            $tags = preg_split('/[|,]/', $tags) ?: [];
        }

        return collect($tags)
            ->map(function ($tag) {
                if ($tag instanceof Term) {
                    return $tag->slug();
                }

                if (is_array($tag)) {
                    return $tag['slug'] ?? null;
                }

                return is_string($tag) ? $tag : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function indexFromResolver(string $collection, Carbon $from, ?Carbon $to, ?int $limit, $tags): array
    {
        $query = Entry::query()->where('collection', $collection);

        if ($tags) {
            $tagSlugs = $this->normalizeTagSlugs($tags);
            $taxonomy = (string) config('statamic-calendar.fields.tags.handle', 'event_tags');
            $prefixedSlugs = collect($tagSlugs)
                ->map(fn ($slug) => "{$taxonomy}::{$slug}")
                ->all();

            if ($prefixedSlugs) {
                $query->whereTaxonomyIn($prefixedSlugs);
            }
        }

        $entries = $query->get();

        $allOccurrences = collect();

        foreach ($entries as $entry) {
            $occurrences = $this->resolver->resolve($entry, $from, $to, $limit);
            $allOccurrences = $allOccurrences->merge($occurrences);
        }

        $allOccurrences = $allOccurrences->sortBy(fn (Occurrence $o) => $o->start);

        if ($limit) {
            $allOccurrences = $allOccurrences->take($limit);
        }

        return $allOccurrences->map(fn (Occurrence $o) => $this->occurrenceToArray($o))->values()->all();
    }

    private function getContextStart(): ?Carbon
    {
        $contextStart = $this->context->get('start');

        if ($contextStart instanceof Carbon) {
            return $contextStart;
        }

        if ($contextStart instanceof \Statamic\Fields\Value) {
            $value = $contextStart->value();

            return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        }

        return null;
    }

    private function occurrenceToArray(Occurrence $occurrence): array
    {
        $augmented = $occurrence->event->toAugmentedArray();

        return array_merge($augmented, [
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'url' => $occurrence->url(),
        ]);
    }

    private function occurrenceDataToArray(OccurrenceData $occurrence): array
    {
        return [
            'id' => $occurrence->eventId,
            'title' => $occurrence->title,
            'slug' => $occurrence->slug,
            'teaser' => $occurrence->teaser,
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'url' => $occurrence->url,
        ];
    }
}
