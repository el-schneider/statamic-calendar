<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

beforeEach(function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    view()->addLocation(__DIR__.'/../__fixtures__/views');
    Collection::make('events')->sites(['default', 'de', 'fr'])->template('occurrence')->save();

    // The same slug in every site, so only the site tells the entries apart.
    $this->events = collect(['default' => 'Meetup', 'de' => 'Treffen', 'fr' => 'Rencontre'])
        ->map(function (string $title, string $site) {
            $entry = Entry::make()
                ->collection('events')
                ->locale($site)
                ->slug('meetup')
                ->published(true)
                ->data([
                    'title' => $title,
                    'layout' => false,
                    'dates' => [['start_date' => '2026-07-18', 'start_time' => '10:00']],
                ]);

            $entry->save();

            return $entry;
        });
});

afterEach(function () {
    Carbon::setTestNow();
    File::deleteDirectory(__DIR__.'/../__fixtures__/content/collections/events');
    File::deleteDirectory(__DIR__.'/../__fixtures__/users');
    File::delete(__DIR__.'/../__fixtures__/content/collections/events.yaml');
});

test('occurrence urls carry the site path or domain', function (string $site, string $url, string $absoluteUrl) {
    expect($this->events[$site]->url())->toBe($url)
        ->and($this->events[$site]->absoluteUrl())->toBe($absoluteUrl);
})->with([
    'root site' => ['default', '/calendar/2026/07/18/meetup', 'http://localhost/calendar/2026/07/18/meetup'],
    'subdirectory site' => ['de', '/de/calendar/2026/07/18/meetup', 'http://localhost/de/calendar/2026/07/18/meetup'],
    'domain site' => ['fr', '/calendar/2026/07/18/meetup', 'http://example.fr/calendar/2026/07/18/meetup'],
]);

test('each site resolves its own entry for a shared slug', function (string $url, string $title) {
    $response = $this->get($url)->assertOk()->assertSee($title);

    collect(['Meetup', 'Treffen', 'Rencontre'])
        ->reject($title)
        ->each(fn (string $other) => $response->assertDontSee($other));
})->with([
    'root site' => ['http://localhost/calendar/2026/07/18/meetup', 'Meetup'],
    'subdirectory site' => ['http://localhost/de/calendar/2026/07/18/meetup', 'Treffen'],
    'domain site' => ['http://example.fr/calendar/2026/07/18/meetup', 'Rencontre'],
]);

test('expired occurrences redirect within their site', function (string $site, string $from, string $to) {
    $this->events[$site]
        ->set('dates', [['start_date' => '2026-07-04', 'start_time' => '10:00', 'is_recurring' => true, 'frequency' => 'weekly']])
        ->save();

    $this->get($from)->assertRedirect($to);
})->with([
    'subdirectory site' => ['de', 'http://localhost/de/calendar/2026/07/04/meetup', 'http://localhost/de/calendar/2026/07/18/meetup'],
    'domain site' => ['fr', 'http://example.fr/calendar/2026/07/04/meetup', 'http://example.fr/calendar/2026/07/18/meetup'],
]);

test('localizing onto a slug another entry uses in that site fails', function () {
    $origin = Entry::make()->collection('events')->locale('default')->slug('party')->data(['title' => 'Party']);
    $origin->save();
    Entry::make()->collection('events')->locale('de')->slug('party')->data(['title' => 'Andere Party'])->save();

    $this->actingAs(User::make()->email('admin@example.com')->makeSuper()->save())
        ->postJson(cp_route('collections.entries.localize', ['events', $origin->id()]), ['site' => 'de'])
        ->assertJsonValidationErrors('slug');

    expect(Entry::query()->where('collection', 'events')->where('site', 'de')->where('slug', 'party')->count())->toBe(1);
});

test('localizing keeps the slug when the site has no conflict', function () {
    $origin = Entry::make()->collection('events')->locale('default')->slug('party')->data(['title' => 'Party']);
    $origin->save();

    $origin->makeLocalization('de')->save();

    expect(Entry::query()->where('collection', 'events')->where('slug', 'party')->count())->toBe(2);
});

test('a propagating origin is rejected before anything is written when a target site uses the slug', function () {
    Entry::make()->collection('events')->locale('de')->slug('party')->data(['title' => 'Andere Party'])->save();
    Collection::find('events')->propagate(true)->save();

    $origin = Entry::make()->collection('events')->locale('default')->slug('party')->data(['title' => 'Party']);

    expect(fn () => $origin->save())->toThrow(ValidationException::class)
        ->and(Entry::query()->where('collection', 'events')->where('slug', 'party')->count())->toBe(1);
});

test('a propagating origin creates its localizations when every site is free', function () {
    Collection::find('events')->propagate(true)->save();

    Entry::make()->collection('events')->locale('default')->slug('party')->data(['title' => 'Party'])->save();

    expect(Entry::query()->where('collection', 'events')->where('slug', 'party')->pluck('site')->sort()->values()->all())
        ->toBe(['de', 'default', 'fr']);
});
