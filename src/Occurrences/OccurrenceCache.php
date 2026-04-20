<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;

class OccurrenceCache
{
    /**
     * @return Collection<int, OccurrenceData>
     */
    public function all(): Collection
    {
        if (! $this->isBuilt()) {
            $this->rebuild();
        }

        $cached = Cache::get($this->cacheKey(), []);

        return collect($cached)->map(fn (array $data) => OccurrenceData::fromArray($data));
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function on(Carbon $date): Collection
    {
        return $this->all()->filter(
            fn (OccurrenceData $o) => $o->start->isSameDay($date)
        )->values();
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function between(Carbon $from, Carbon $to): Collection
    {
        return $this->all()->filter(
            fn (OccurrenceData $o) => $o->start->gte($from) && $o->start->lte($to)
        )->values();
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function forEntry(string|int $entryId): Collection
    {
        $entryId = (string) $entryId;

        return $this->all()->filter(
            fn (OccurrenceData $o) => $o->entryId === $entryId
        )->values();
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function forOrganizer(string|int|null $organizerId): Collection
    {
        $organizerId = $organizerId !== null ? (string) $organizerId : null;

        return $this->all()->filter(
            fn (OccurrenceData $o) => $o->organizerId === $organizerId
        )->values();
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function upcoming(int $limit = 10): Collection
    {
        $now = Carbon::now();

        return $this->all()
            ->filter(fn (OccurrenceData $o) => $o->start->gte($now))
            ->sortBy(fn (OccurrenceData $o) => $o->start)
            ->take($limit)
            ->values();
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function isBuilt(): bool
    {
        return Cache::has($this->cacheKey());
    }

    public function rebuild(): void
    {
        /** @var OccurrenceResolver $resolver */
        $resolver = App::make(OccurrenceResolver::class);
        $entries = Entry::query()->where('collection', $this->collection())->get();

        $to = Carbon::now()->addDays($this->daysAhead());

        $occurrences = collect();

        foreach ($entries as $entry) {
            $dates = $entry->get($this->datesField()) ?? [];

            if (empty($dates)) {
                continue;
            }

            $eventFrom = collect($dates)
                ->filter(fn ($d) => is_array($d) && ! empty($d['start_date']))
                ->map(fn ($d) => Carbon::parse((string) $d['start_date']))
                ->sortBy(fn (Carbon $date) => $date->timestamp)
                ->first();

            if (! $eventFrom) {
                continue;
            }

            $eventOccurrences = $resolver->resolve($entry, $eventFrom, $to);

            $entryId = (string) $entry->id();
            $organizerData = $this->extractOrganizerData($entry);

            foreach ($eventOccurrences as $occurrence) {
                $occurrences->push([
                    'id' => OccurrenceData::composeId($entryId, $occurrence->start),
                    'entry_id' => $entryId,
                    'title' => (string) $entry->get('title'),
                    'slug' => $entry->slug(),
                    'teaser' => $this->extractTeaser($entry),
                    'organizer_id' => $organizerData['id'],
                    'organizer_slug' => $organizerData['slug'],
                    'organizer_title' => $organizerData['title'],
                    'organizer_url' => $organizerData['url'],
                    'tags' => $this->extractTags($entry),
                    'start' => $occurrence->start->toIso8601String(),
                    'end' => $occurrence->end?->toIso8601String(),
                    'is_all_day' => $occurrence->isAllDay,
                    'is_recurring' => $occurrence->isRecurring,
                    'recurrence_description' => $occurrence->recurrenceDescription,
                    'url' => $occurrence->url(),
                ]);
            }
        }

        $occurrences = $occurrences->sortByDesc('start')->values();

        Cache::forever($this->cacheKey(), $occurrences->all());
    }

    private function extractTeaser($entry): ?string
    {
        $teaserHandle = (string) config('statamic-calendar.fields.teaser', 'teaser');
        $teaser = $entry->get($teaserHandle);
        if ($teaser) {
            return (string) $teaser;
        }

        $fallbackHandle = (string) config('statamic-calendar.fields.teaser_fallback', 'description');
        $fallback = $entry->get($fallbackHandle);
        if ($fallback) {
            return Str::limit(strip_tags((string) $fallback), 160);
        }

        return null;
    }

    /**
     * @return array{id: ?string, slug: ?string, title: ?string, url: ?string}
     */
    private function extractOrganizerData($entry): array
    {
        $nullOrganizer = ['id' => null, 'slug' => null, 'title' => null, 'url' => null];

        $handle = config('statamic-calendar.fields.organizer.handle');
        if (! $handle) {
            return $nullOrganizer;
        }

        $organizer = $entry->get((string) $handle);

        if (! $organizer) {
            return $nullOrganizer;
        }

        if (is_string($organizer) || is_int($organizer)) {
            $organizer = Entry::find((string) $organizer);
            if (! $organizer) {
                return array_merge($nullOrganizer, ['id' => (string) $entry->get((string) $handle)]);
            }
        }

        if (is_object($organizer) && method_exists($organizer, 'id') && method_exists($organizer, 'slug')) {
            return [
                'id' => (string) $organizer->id(),
                'slug' => $organizer->slug(),
                'title' => $organizer->get('title'),
                'url' => $organizer->url(),
            ];
        }

        return $nullOrganizer;
    }

    /**
     * @return array<string>
     */
    private function extractTags($entry): array
    {
        $handle = (string) config('statamic-calendar.fields.tags.handle', 'event_tags');
        $tags = $entry->get($handle);

        if (! $tags) {
            return [];
        }

        return collect($tags)
            ->map(fn ($tag) => match (true) {
                is_string($tag) => $tag,
                is_object($tag) && method_exists($tag, 'slug') => $tag->slug(),
                is_array($tag) => $tag['slug'] ?? null,
                default => null,
            })
            ->filter()
            ->values()
            ->all();
    }

    private function collection(): string
    {
        return (string) config('statamic-calendar.collection', 'events');
    }

    private function cacheKey(): string
    {
        return (string) config('statamic-calendar.cache.key', 'statamic_calendar.occurrences');
    }

    private function daysAhead(): int
    {
        return (int) config('statamic-calendar.cache.days_ahead', 365);
    }

    private function datesField(): string
    {
        return (string) config('statamic-calendar.fields.dates', 'dates');
    }
}
