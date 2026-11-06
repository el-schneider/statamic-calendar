<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Http\Controllers;

use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Ics\IcsGenerator;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Illuminate\Http\Response;
use Statamic\Auth\Protect\Protection;
use Statamic\Facades\Entry;

/**
 * Both endpoints export public entries only. An entry under any protection
 * scheme — its own or the site-wide default — is omitted from the feed and
 * cannot be downloaded, because these routes bypass Statamic's entry response
 * and would otherwise hand out restricted content to anyone holding the URL.
 * Serving restricted exports (after a password or login) is the host app's
 * job: point its own route at its own controller.
 */
class IcsController
{
    public function __construct(
        private IcsGenerator $generator
    ) {}

    /**
     * Full calendar feed — subscribable by calendar apps.
     */
    public function feed(): Response
    {
        $name = (string) config('statamic-calendar.ics.calendar_name', config('app.name', 'Calendar'));

        $occurrences = Occurrences::all()->filter(
            fn (OccurrenceData $o) => $this->isPublic($o->entryId)
        )->values();

        $body = $this->generator->feed($occurrences, $name);

        return $this->icsResponse($body);
    }

    /**
     * Single occurrence download — "Add to calendar" button.
     *
     * The occurrence ID format is "{entry_id}-{Y-m-d-His}".
     */
    public function download(string $occurrenceId): Response
    {
        $occurrence = Occurrences::all()->first(
            fn (OccurrenceData $o) => $o->id === $occurrenceId
        );

        if (! $occurrence || ! $this->isPublic($occurrence->entryId)) {
            abort(404);
        }

        $body = $this->generator->single($occurrence);
        $filename = str_replace(['/', '\\', ' '], '-', $occurrence->slug).'.ics';

        return $this->icsResponse($body, $filename);
    }

    /**
     * Read live from the entry, never from the occurrence cache: a cached
     * occurrence can predate the protection that was just added to its entry.
     */
    private function isPublic(string $entryId): bool
    {
        $entry = Entry::find($entryId);

        return (bool) ($entry
            && $entry->published()
            && ! app(Protection::class)->setData($entry)->scheme());
    }

    private function icsResponse(string $body, ?string $downloadFilename = null): Response
    {
        $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];

        if ($downloadFilename) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$downloadFilename.'"';
        }

        return new Response($body, 200, $headers);
    }
}
