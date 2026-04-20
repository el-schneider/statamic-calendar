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
     * @param  string|null  $teaser  Short description for listing views
     * @param  array<string>  $tags  Tag slugs for filtering
     * @param  bool  $isExcluded  Occurrence was cancelled or rescheduled away from this date.
     *                            Hidden from reads by default — opt in via `include_excluded`.
     * @param  ?Carbon  $replacementDate  For excluded occurrences: the date the event moved to.
     *                                    Null when the occurrence was cancelled outright.
     * @param  ?Carbon  $replacesDate  For non-excluded occurrences that replace a rescheduled one:
     *                                 the original date this occurrence replaces.
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
        public bool $isExcluded = false,
        public ?Carbon $replacementDate = null,
        public ?Carbon $replacesDate = null,
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
            isExcluded: (bool) ($data['is_excluded'] ?? false),
            replacementDate: ! empty($data['replacement_date']) ? Carbon::parse($data['replacement_date']) : null,
            replacesDate: ! empty($data['replaces_date']) ? Carbon::parse($data['replaces_date']) : null,
        );
    }

    public function toArray(): array
    {
        return [
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
            'is_excluded' => $this->isExcluded,
            'replacement_date' => $this->replacementDate?->toIso8601String(),
            'replaces_date' => $this->replacesDate?->toIso8601String(),
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
