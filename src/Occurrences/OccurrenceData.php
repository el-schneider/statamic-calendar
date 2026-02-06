<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;

/**
 * Immutable DTO representing a cached event occurrence.
 */
readonly class OccurrenceData
{
    /**
     * @param  string|null  $teaser  Short description for listing views
     * @param  array<string>  $tags  Tag slugs for filtering
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public string $title,
        public string $slug,
        public ?string $teaser,
        public ?string $organizerId,
        public ?string $organizerSlug,
        public ?string $organizerTitle,
        public ?string $organizerUrl,
        public array $tags,
        public Carbon $start,
        public ?Carbon $end,
        public bool $isAllDay,
        public bool $isRecurring,
        public ?string $recurrenceDescription,
        public string $url,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            eventId: $data['event_id'],
            title: $data['title'],
            slug: $data['slug'],
            teaser: $data['teaser'] ?? null,
            organizerId: $data['organizer_id'] ?? null,
            organizerSlug: $data['organizer_slug'] ?? null,
            organizerTitle: $data['organizer_title'] ?? null,
            organizerUrl: $data['organizer_url'] ?? null,
            tags: $data['tags'] ?? ($data['event_tags'] ?? []),
            start: Carbon::parse($data['start']),
            end: ! empty($data['end']) ? Carbon::parse($data['end']) : null,
            isAllDay: (bool) $data['is_all_day'],
            isRecurring: (bool) $data['is_recurring'],
            recurrenceDescription: $data['recurrence_description'] ?? null,
            url: $data['url'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->eventId,
            'title' => $this->title,
            'slug' => $this->slug,
            'teaser' => $this->teaser,
            'organizer_id' => $this->organizerId,
            'organizer_slug' => $this->organizerSlug,
            'organizer_title' => $this->organizerTitle,
            'organizer_url' => $this->organizerUrl,
            'tags' => $this->tags,
            'start' => $this->start->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
            'is_all_day' => $this->isAllDay,
            'is_recurring' => $this->isRecurring,
            'recurrence_description' => $this->recurrenceDescription,
            'url' => $this->url,
        ];
    }

    /**
     * @param  array<string>  $slugs
     */
    public function hasAnyTag(array $slugs): bool
    {
        return ! empty(array_intersect($this->tags, $slugs));
    }
}
