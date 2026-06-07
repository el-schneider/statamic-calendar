<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Http\Controllers\OccurrenceController;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Statamic\Facades\Entry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
