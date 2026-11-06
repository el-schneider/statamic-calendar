<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01 09:00:00');
    Occurrences::clear();
});

afterEach(function () {
    Carbon::setTestNow();
    Occurrences::clear();

    File::delete([
        __DIR__.'/../__fixtures__/content/collections/events.yaml',
        __DIR__.'/../__fixtures__/content/collections/events/public-event.md',
        __DIR__.'/../__fixtures__/content/collections/events/protected-event.md',
    ]);
});

function icsEvent(string $id, array $data = []): Statamic\Contracts\Entries\Entry
{
    $collection = Collection::find('events') ?? Collection::make('events');
    $collection->save();

    $entry = Entry::make()
        ->id($id)
        ->collection($collection)
        ->locale('default')
        ->slug($id)
        ->published(true)
        ->data(array_merge([
            'title' => str($id)->replace('-', ' ')->title()->toString(),
            'dates' => [[
                'start_date' => '2026-06-10',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'is_all_day' => false,
                'is_recurring' => false,
            ]],
        ], $data));

    $entry->save();

    return $entry;
}

test('the feed exports public entries only', function () {
    icsEvent('public-event');
    icsEvent('protected-event', ['protect' => 'password', 'password' => 'letmein']);

    $this->get('/calendar.ics')
        ->assertOk()
        ->assertSee('SUMMARY:Public Event')
        ->assertDontSee('Protected Event');
});

test('downloads are refused for protected entries', function () {
    icsEvent('public-event');
    icsEvent('protected-event', ['protect' => 'password', 'password' => 'letmein']);

    $public = Occurrences::forEntry('public-event')->first();
    $protected = Occurrences::forEntry('protected-event')->first();

    $this->get('/calendar.ics/'.$public->id)
        ->assertOk()
        ->assertSee('SUMMARY:Public Event');

    $this->get('/calendar.ics/'.$protected->id)->assertNotFound();
});

test('protection added after the last cache rebuild still blocks exports', function () {
    $entry = icsEvent('protected-event');
    $occurrence = Occurrences::forEntry('protected-event')->first();

    $this->get('/calendar.ics/'.$occurrence->id)->assertOk();

    // Written straight to the entry so the occurrence cache keeps the stale,
    // unprotected copy that the endpoints must not trust.
    $entry->set('protect', 'password')->set('password', 'letmein')->saveQuietly();

    $this->get('/calendar.ics/'.$occurrence->id)->assertNotFound();
    $this->get('/calendar.ics')->assertOk()->assertDontSee('Protected Event');
});
