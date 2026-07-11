<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

beforeEach(function () {
    config()->set('statamic-calendar.url.strategy', 'date_segments');
});

afterEach(function () {
    File::delete([
        __DIR__.'/../__fixtures__/content/collections/articles.yaml',
        __DIR__.'/../__fixtures__/content/collections/events.yaml',
    ]);
});

function calendarEntry(array $dates, string $slug = 'community-meetup'): EntryContract
{
    $collection = Collection::find('events') ?? Collection::make('events');
    $collection->save();

    return Entry::make()
        ->collection($collection)
        ->locale('default')
        ->slug($slug)
        ->data(['dates' => $dates]);
}

test('event urls use the next occurrence', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $entry = calendarEntry([
        ['start_date' => '2026-07-10', 'start_time' => '18:00'],
        ['start_date' => '2026-07-12', 'start_time' => '18:00'],
        ['start_date' => '2026-07-13', 'start_time' => '18:00'],
    ]);

    expect($entry->url())->toBe('/calendar/2026/07/12/community-meetup');
});

test('query string event urls retain the native entry route', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');
    config()->set('statamic-calendar.url.strategy', 'query_string');

    $collection = Collection::make('events')->routes('/events/{slug}');
    $collection->save();

    $entry = calendarEntry([
        ['start_date' => '2026-07-12', 'start_time' => '18:00'],
    ]);

    expect($entry->url())->toBe('/events/community-meetup?date=2026-07-12');
});

test('event urls fall back to the most recent occurrence', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $entry = calendarEntry([
        ['start_date' => '2026-07-09', 'start_time' => '18:00'],
        ['start_date' => '2026-07-10', 'start_time' => '18:00'],
    ]);

    expect($entry->url())->toBe('/calendar/2026/07/10/community-meetup');
});

test('live preview urls resolve supplemented draft dates', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $entry = calendarEntry([
        ['start_date' => '2026-07-12', 'start_time' => '18:00'],
    ]);

    $entry->setSupplement('dates', [
        ['start_date' => '2026-08-03', 'start_time' => '18:00'],
    ]);

    expect($entry->url())->toBe('/calendar/2026/08/03/community-meetup');
});

test('event entries without valid dates have no public or preview url', function () {
    $entry = calendarEntry([
        ['start_date' => null],
    ]);

    expect($entry->url())->toBeNull()
        ->and($entry->absoluteUrl())->toBeNull()
        ->and($entry->livePreviewUrl())->toBeNull();
});

test('non-event entries retain native urls', function () {
    $collection = Collection::make('articles')->routes('/articles/{slug}');
    $collection->save();

    $entry = Entry::make()
        ->collection($collection)
        ->locale('default')
        ->slug('news');

    expect($entry->url())->toBe('/articles/news');
});
