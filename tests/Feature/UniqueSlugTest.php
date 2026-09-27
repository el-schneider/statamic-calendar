<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

beforeEach(function () {
    config()->set('statamic-calendar.url.strategy', 'date_segments');
});

afterEach(function () {
    File::deleteDirectory(__DIR__.'/../__fixtures__/content/collections/events');
    File::deleteDirectory(__DIR__.'/../__fixtures__/content/collections/articles');
    File::deleteDirectory(__DIR__.'/../__fixtures__/users');
    File::delete([
        __DIR__.'/../__fixtures__/content/collections/events.yaml',
        __DIR__.'/../__fixtures__/content/collections/articles.yaml',
    ]);
});

function saveEntry(string $collection, string $slug, ?string $id = null): Statamic\Contracts\Entries\Entry
{
    Collection::find($collection) ?? Collection::make($collection)->save();

    $entry = Entry::make()->collection($collection)->slug($slug);

    if ($id) {
        $entry->id($id);
    }

    $entry->save();

    return $entry;
}

test('the control panel reports a duplicate calendar slug on the slug field', function () {
    Collection::make('events')->save();
    $this->actingAs(User::make()->email('admin@example.com')->makeSuper()->save());

    $create = fn () => $this->postJson(cp_route('collections.entries.store', ['events', 'default']), [
        'title' => 'Meetup',
        'slug' => 'meetup',
        '_blueprint' => Collection::find('events')->entryBlueprint()->handle(),
    ]);

    $create()->assertOk();
    $create()->assertJsonValidationErrors('slug');

    expect(Entry::query()->where('collection', 'events')->count())->toBe(1);
});

test('saving a calendar entry with a taken slug fails', function () {
    saveEntry('events', 'meetup');

    expect(fn () => saveEntry('events', 'meetup'))->toThrow(ValidationException::class);
});

test('a calendar entry can be saved again under its own slug', function () {
    $entry = saveEntry('events', 'meetup', 'meetup');

    $entry->set('title', 'Renamed')->save();

    expect(Entry::find('meetup')->get('title'))->toBe('Renamed');
});

test('query string urls leave slugs to the collection route', function () {
    config()->set('statamic-calendar.url.strategy', 'query_string');

    saveEntry('events', 'meetup');
    saveEntry('events', 'meetup');

    expect(Entry::query()->where('collection', 'events')->count())->toBe(2);
});

test('other collections allow duplicate slugs', function () {
    saveEntry('articles', 'news');
    saveEntry('articles', 'news');

    expect(Entry::query()->where('collection', 'articles')->count())->toBe(2);
});
