<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Listeners;

use ElSchneider\StatamicCalendar\Facades\Occurrences;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;

class RebuildOccurrenceCacheOnEntryChange
{
    public function handle(EntrySaved|EntryDeleted $event): void
    {
        $collection = (string) config('statamic-calendar.collection', 'events');

        if ($event->entry->collection()?->handle() !== $collection) {
            return;
        }

        Occurrences::rebuild();
    }
}
