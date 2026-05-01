<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Events\OccurrenceBuilding;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Statamic\Facades\Entry;

beforeEach(function () {
    Carbon::setTestNow('2026-03-01 00:00:00');
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

test('rebuild persists OccurrenceBuilding extras without letting them shadow core fields', function () {
    Event::listen(OccurrenceBuilding::class, function (OccurrenceBuilding $e) {
        $e->extra['image'] = ['url' => '/assets/foo.jpg'];
        $e->extra['category'] = $e->entry->slug();
        $e->extra['title'] = 'Shadowed title';
    });

    // Occurrence::__construct types $entry as the concrete class, not the contract.
    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('slug')->andReturn('demo-event');
    $entry->shouldReceive('url')->andReturn('/events/demo-event');
    $entry->shouldReceive('get')->with('dates')->andReturn([['start_date' => '2026-03-10']]);
    $entry->shouldReceive('get')->with('title')->andReturn('Original title');
    $entry->shouldReceive('get')->andReturn(null);

    $builder = Mockery::mock();
    $builder->shouldReceive('where')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([$entry]));
    Entry::shouldReceive('query')->andReturn($builder);

    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('resolve')->andReturn(collect([
        new Occurrence($entry, Carbon::parse('2026-03-10 10:00'), null, false, false),
    ]));
    app()->instance(OccurrenceResolver::class, $resolver);

    app(OccurrenceCache::class)->rebuild();

    $occurrence = app(OccurrenceCache::class)->all()->first();

    expect($occurrence->title)->toBe('Original title')
        ->and($occurrence->extra)->toBe([
            'image' => ['url' => '/assets/foo.jpg'],
            'category' => 'demo-event',
        ]);
});
