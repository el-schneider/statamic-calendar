<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Entries\Concerns;

use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;

/**
 * Add to an existing custom Statamic Entry class when the site already uses
 * one. The addon will not replace custom entry classes.
 */
trait HasCalendarUrls
{
    public function url()
    {
        return $this->isCalendarEntry()
            ? $this->calendarOccurrenceUrl()
            : parent::url();
    }

    public function absoluteUrl()
    {
        return $this->isCalendarEntry()
            ? $this->calendarOccurrenceUrl(absolute: true)
            : parent::absoluteUrl();
    }

    public function livePreviewUrl()
    {
        if (! $this->isCalendarEntry()) {
            return parent::livePreviewUrl();
        }

        return $this->representativeOccurrence()
            ? $this->cpUrl('collections.entries.preview.edit')
            : null;
    }

    private function calendarOccurrenceUrl(bool $absolute = false): ?string
    {
        if (! $occurrence = $this->representativeOccurrence()) {
            return null;
        }

        if (config('statamic-calendar.url.strategy', 'date_segments') === 'query_string') {
            $entryUrl = $absolute ? parent::absoluteUrl() : parent::url();

            return $entryUrl ? $occurrence->url($entryUrl) : null;
        }

        $url = $occurrence->url();

        if (! $absolute) {
            return $url;
        }

        return rtrim($this->site()->absoluteUrl(), '/').'/'.ltrim($url, '/');
    }

    private function representativeOccurrence(): ?Occurrence
    {
        return app(OccurrenceResolver::class)->representative($this);
    }

    private function isCalendarEntry(): bool
    {
        return $this->collectionHandle() === config('statamic-calendar.collection', 'events');
    }
}
