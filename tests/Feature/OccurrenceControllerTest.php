<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Http\Controllers\OccurrenceController;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Illuminate\Http\Request;
use Statamic\Contracts\Tokens\Token;
use Statamic\CP\LivePreview;
use Statamic\Facades\Entry;
use Statamic\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('occurrence route renders tokenized unsaved preview values', function () {
    config()->set('statamic-calendar.url.strategy', 'date_segments');

    $token = Mockery::mock(Token::class);
    $request = Mockery::mock(Request::class)->shouldIgnoreMissing();
    $request->shouldReceive('isLivePreview')->andReturnTrue();
    $request->shouldReceive('statamicToken')->andReturn($token);
    app()->instance('request', $request);

    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('events');
    $entry->shouldReceive('slug')->andReturn('draft-event');
    $entry->shouldReceive('toAugmentedArray')->andReturn(['title' => 'Unsaved title']);
    $entry->shouldReceive('template')->andReturn('default');
    $entry->shouldReceive('layout')->andReturn('layout');

    $livePreview = Mockery::mock(LivePreview::class);
    $livePreview->shouldReceive('item')->with($token)->andReturn($entry);
    app()->instance(LivePreview::class, $livePreview);

    $occurrence = new Occurrence(
        $entry,
        Carbon::parse('2026-08-03 10:00'),
        null,
        false,
        false,
    );

    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('findOccurrenceOnDate')
        ->with($entry, Mockery::on(fn (Carbon $date) => $date->toDateString() === '2026-08-03'))
        ->andReturn($occurrence);

    $response = (new OccurrenceController($resolver))->show(2026, 8, 3, 'draft-event');

    expect($response)->toBeInstanceOf(View::class);
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
