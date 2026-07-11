<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Http\Controllers\OccurrenceController;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Statamic\CP\LivePreview;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

afterEach(function () {
    File::delete([
        __DIR__.'/../__fixtures__/content/collections/events.yaml',
        __DIR__.'/../__fixtures__/content/collections/events/draft-event.md',
    ]);
});

test('occurrence route renders tokenized unsaved preview values', function () {
    config()->set('statamic-calendar.url.strategy', 'date_segments');

    $collection = Collection::make('events');
    $collection->save();

    $entry = Entry::make()
        ->id('draft-event')
        ->collection($collection)
        ->locale('default')
        ->slug('draft-event')
        ->published(false)
        ->template('statamic-calendar/show')
        ->data([
            'title' => 'Saved title',
            'dates' => [['start_date' => '2026-07-01', 'start_time' => '10:00']],
        ]);

    $entry->setSupplement('title', 'Unsaved title');
    $entry->setSupplement('dates', [
        ['start_date' => '2026-08-03', 'start_time' => '10:00'],
    ]);

    $token = app(LivePreview::class)->tokenize('calendar-preview-test', $entry);

    app()->instance('request', Request::create(
        '/calendar/2026/08/03/draft-event',
        'GET',
        ['token' => $token->token()],
    ));

    $response = app(OccurrenceController::class)->show(2026, 8, 3, 'draft-event');

    expect((string) $response->data()['title'])->toBe('Unsaved title')
        ->and($response->data()['start']->toDateString())->toBe('2026-08-03');

    expect(fn () => app(OccurrenceController::class)->show(2026, 8, 3, 'another-event'))
        ->toThrow(NotFoundHttpException::class);
});

test('invalid preview tokens do not expose draft entries', function () {
    $collection = Collection::make('events');
    $collection->save();

    Entry::make()
        ->id('draft-event')
        ->collection($collection)
        ->locale('default')
        ->slug('draft-event')
        ->published(false)
        ->data([
            'title' => 'Private draft',
            'dates' => [['start_date' => '2026-08-03', 'start_time' => '10:00']],
        ])
        ->save();

    app()->instance('request', Request::create(
        '/calendar/2026/08/03/draft-event',
        'GET',
        ['token' => 'invalid-preview-token'],
    ));

    expect(fn () => app(OccurrenceController::class)->show(2026, 8, 3, 'draft-event'))
        ->toThrow(NotFoundHttpException::class);
});

test('occurrence route aborts for unpublished entries', function () {
    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('published')->andReturnFalse();

    $builder = Mockery::mock();
    $builder->shouldReceive('where')->with('collection', 'events')->andReturnSelf();
    $builder->shouldReceive('where')->with('slug', 'draft-event')->andReturnSelf();
    $builder->shouldReceive('first')->andReturn($entry);
    Entry::shouldReceive('query')->andReturn($builder);

    $controller = new OccurrenceController(Mockery::mock(OccurrenceResolver::class));

    expect(fn () => $controller->show(2026, 3, 10, 'draft-event'))
        ->toThrow(NotFoundHttpException::class);
});
