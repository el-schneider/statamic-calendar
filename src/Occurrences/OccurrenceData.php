<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;

/**
 * Immutable DTO representing a cached occurrence.
 */
readonly class OccurrenceData
{
    /**
     * Keys managed by the DTO itself. Anything else on a stored payload
     * lands in `$extra` on rehydration.
     */
    private const RESERVED_KEYS = [
        'id',
        'entry_id',
        'title',
        'slug',
        'teaser',
        'organizer_id',
        'organizer_slug',
        'organizer_title',
        'organizer_url',
        'tags',
        'start',
        'end',
        'is_all_day',
        'is_recurring',
        'recurrence_description',
        'url',
    ];

    /**
     * @param  string|null  $teaser  Short description for listing views
     * @param  array<string>  $tags  Tag slugs for filtering
     * @param  array<string, mixed>  $extra  Extra fields contributed by `OccurrenceBuilding`
     *                                       listeners at cache build time. Splatted onto `toArray()`
     *                                       so they surface in API and tag output.
     */
    public function __construct(
        public string $id,
        public string $entryId,
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
        public array $extra = [],
    ) {}

    /**
     * Composes the stable occurrence id used for .ics downloads and cache
     * keys. Single source of truth — all call sites go through this.
     */
    public static function composeId(string|int $entryId, Carbon $start): string
    {
        return ((string) $entryId).'-'.$start->format('Y-m-d-His');
    }

    public static function fromArray(array $data): self
    {
        $extra = array_diff_key($data, array_flip(self::RESERVED_KEYS));

        return new self(
            id: (string) $data['id'],
            entryId: (string) $data['entry_id'],
            title: $data['title'],
            slug: $data['slug'],
            teaser: $data['teaser'] ?? null,
            organizerId: isset($data['organizer_id']) ? (string) $data['organizer_id'] : null,
            organizerSlug: $data['organizer_slug'] ?? null,
            organizerTitle: $data['organizer_title'] ?? null,
            organizerUrl: $data['organizer_url'] ?? null,
            tags: $data['tags'] ?? [],
            start: Carbon::parse($data['start']),
            end: ! empty($data['end']) ? Carbon::parse($data['end']) : null,
            isAllDay: (bool) $data['is_all_day'],
            isRecurring: (bool) $data['is_recurring'],
            recurrenceDescription: $data['recurrence_description'] ?? null,
            url: $data['url'],
            extra: $extra,
        );
    }

    public function toArray(): array
    {
        // Reserved keys last: collisions from $extra get silently shadowed.
        return [
            ...$this->extra,
            'id' => $this->id,
            'entry_id' => $this->entryId,
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
