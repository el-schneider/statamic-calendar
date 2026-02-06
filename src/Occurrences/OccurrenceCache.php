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
    public function forEvent(string $eventId): Collection
    {
        return $this->all()->filter(
            fn (OccurrenceData $o) => $o->eventId === $eventId
        )->values();
    }

    /**
     * @return Collection<int, OccurrenceData>
     */
    public function forOrganizer(?string $organizerId): Collection
    {
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
            $startDateKey = 'start_date';
            if (empty($dates)) {
                continue;
            }

            $eventFrom = collect($dates)
                ->filter(fn ($d) => is_array($d) && ! empty($d[$startDateKey]))
                ->map(fn ($d) => Carbon::parse((string) $d[$startDateKey]))
                ->sortBy(fn (Carbon $date) => $date->timestamp)
                ->first();

            if (! $eventFrom) {
                continue;
            }

            $eventOccurrences = $resolver->resolve($entry, $eventFrom, $to);

            $organizerData = $this->extractOrganizerData($entry);

            foreach ($eventOccurrences as $occurrence) {
                $occurrences->push([
                    'id' => $entry->id().'-'.$occurrence->start->format('Y-m-d-His'),
                    'event_id' => $entry->id(),
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
        $handle = config('statamic-calendar.fields.organizer.handle');
        if (! $handle) {
            return ['id' => null, 'slug' => null, 'title' => null, 'url' => null];
        }

        $organizer = $entry->get((string) $handle);

        if (! $organizer) {
            return ['id' => null, 'slug' => null, 'title' => null, 'url' => null];
        }

        if (is_object($organizer) && method_exists($organizer, 'id') && method_exists($organizer, 'slug')) {
            return [
                'id' => $organizer->id(),
                'slug' => $organizer->slug(),
                'title' => $organizer->get('title'),
                'url' => $organizer->url(),
            ];
        }

        if (is_string($organizer)) {
            $organizerEntry = Entry::find($organizer);
            if ($organizerEntry) {
                return [
                    'id' => $organizerEntry->id(),
                    'slug' => $organizerEntry->slug(),
                    'title' => $organizerEntry->get('title'),
                    'url' => $organizerEntry->url(),
                ];
            }

            return ['id' => $organizer, 'slug' => null, 'title' => null, 'url' => null];
        }

        return ['id' => null, 'slug' => null, 'title' => null, 'url' => null];
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
            ->map(function ($tag) {
                if (is_string($tag)) {
                    return $tag;
                }

                if (is_object($tag) && method_exists($tag, 'slug')) {
                    return $tag->slug();
                }

                if (is_array($tag)) {
                    return $tag['slug'] ?? null;
                }

                return null;
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
