<?php

declare(strict_types=1);

if (config('statamic-calendar.url.strategy') !== 'date_segments') {
    return;
}

use ElSchneider\StatamicCalendar\Http\Controllers\OccurrenceController;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

$prefix = mb_trim((string) config('statamic-calendar.url.date_segments.prefix', 'calendar'), '/');

// Subdirectory sites (/de/) need the route under their path; domain sites share
// the root one. OccurrenceController resolves the site from the request URL.
$sitePath = fn ($site) => mb_rtrim(URL::makeRelative($site->url()), '/');

foreach (Site::all()->map($sitePath)->unique() as $path) {
    $route = Route::get($path.'/'.$prefix.'/{year}/{month}/{day}/{slug}', [OccurrenceController::class, 'show'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{2}')
        ->where('day', '[0-9]{2}');

    if ($path === $sitePath(Site::default())) {
        $route->name('calendar.occurrence');
    }
}
