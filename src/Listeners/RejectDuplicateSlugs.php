<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Listeners;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;

/**
 * Date segment URLs identify an entry by site and slug, and a calendar
 * collection without a route gets no unique URI check from Statamic. This runs
 * on every evented save (CP forms, localization, imports, code); quiet saves
 * and revision working copies skip it until they are saved or published.
 */
class RejectDuplicateSlugs
{
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if (config('statamic-calendar.url.strategy') !== 'date_segments'
            || $entry->collectionHandle() !== config('statamic-calendar.collection', 'events')
            || ! $entry->slug()) {
            return;
        }

        $taken = Entry::query()
            ->where('collection', $entry->collectionHandle())
            ->whereIn('site', $this->sites($entry)->all())
            ->where('slug', $entry->slug())
            ->get()
            ->contains(fn ($other) => (string) $other->id() !== (string) $entry->id());

        if ($taken) {
            throw ValidationException::withMessages(['slug' => __('statamic::validation.unique_entry_value')]);
        }
    }

    /**
     * A new origin in a propagating collection creates its localizations only
     * after it is written, so their sites have to be free before that.
     */
    private function sites(EntryContract $entry): Collection
    {
        $propagates = ! Entry::find($entry->id())
            && ! $entry->hasOrigin()
            && $entry->collection()->propagate();

        return $propagates ? collect($entry->collection()->sites()) : collect([$entry->locale()]);
    }
}
