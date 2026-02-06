<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Occurrences;

use Carbon\Carbon;
use Statamic\Entries\Entry;
use Throwable;

class Occurrence
{
    public function __construct(
        public readonly Entry $event,
        public readonly Carbon $start,
        public readonly ?Carbon $end,
        public readonly bool $isAllDay,
        public readonly bool $isRecurring,
        public readonly ?string $recurrenceDescription = null,
    ) {}

    public function url(): string
    {
        $strategy = (string) $this->cfg('statamic-calendar.url.strategy', 'date_segments');

        if ($strategy === 'query_string') {
            $param = (string) $this->cfg('statamic-calendar.url.query_string.param', 'date');
            $format = (string) $this->cfg('statamic-calendar.url.query_string.format', 'Y-m-d');

            $separator = str_contains($this->event->url(), '?') ? '&' : '?';

            return $this->event->url().$separator.urlencode($param).'='.urlencode($this->start->format($format));
        }

        $prefix = mb_trim((string) $this->cfg('statamic-calendar.url.date_segments.prefix', 'events'), '/');

        return sprintf(
            '/%s/%s/%s/%s/%s',
            $prefix,
            $this->start->format('Y'),
            $this->start->format('m'),
            $this->start->format('d'),
            $this->event->slug()
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
