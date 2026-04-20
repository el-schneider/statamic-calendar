<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use Statamic\Entries\Entry;
use Throwable;

class Occurrence
{
    /**
     * @param  bool  $isExcluded  The occurrence matches a row in the exclusions grid.
     *                            Excluded occurrences carry no meaningful URL and are
     *                            hidden from reads by default; templates/APIs must opt
     *                            in via `include_excluded` to see them.
     * @param  ?Carbon  $replacementDate  If this excluded occurrence was rescheduled,
     *                                    the date it moved to. Null for simple cancellations.
     * @param  ?Carbon  $replacesDate  If this occurrence is the replacement for a
     *                                 rescheduled exclusion, the original date it replaces.
     */
    public function __construct(
        public readonly Entry $entry,
        public readonly Carbon $start,
        public readonly ?Carbon $end,
        public readonly bool $isAllDay,
        public readonly bool $isRecurring,
        public readonly ?string $recurrenceDescription = null,
        public readonly bool $isExcluded = false,
        public readonly ?Carbon $replacementDate = null,
        public readonly ?Carbon $replacesDate = null,
    ) {}

    public function url(): string
    {
        // Excluded occurrences have no meaningful destination — they represent
        // cancellations or rescheduled-away dates, not routable events.
        if ($this->isExcluded) {
            return '';
        }

        $strategy = (string) $this->cfg('statamic-calendar.url.strategy', 'date_segments');

        if ($strategy === 'query_string') {
            $param = (string) $this->cfg('statamic-calendar.url.query_string.param', 'date');
            $format = (string) $this->cfg('statamic-calendar.url.query_string.format', 'Y-m-d');

            $separator = str_contains($this->entry->url(), '?') ? '&' : '?';

            return $this->entry->url().$separator.urlencode($param).'='.urlencode($this->start->format($format));
        }

        $prefix = mb_trim((string) $this->cfg('statamic-calendar.url.date_segments.prefix', 'calendar'), '/');

        return sprintf(
            '/%s/%s/%s/%s/%s',
            $prefix,
            $this->start->format('Y'),
            $this->start->format('m'),
            $this->start->format('d'),
            $this->entry->slug()
        );
    }

    private function cfg(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
