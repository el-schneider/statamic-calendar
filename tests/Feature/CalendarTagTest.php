<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use ElSchneider\StatamicCalendar\Tags\Calendar;
use Statamic\Contracts\View\Antlers\Parser;
use Statamic\Contracts\Entries\Entry as Entry;
use Statamic\Facades\Entry as EntryFacade;

beforeEach(function () {
    Carbon::setTestNow('2026-02-01 00:00:00');

    $visible = calendarTagOccurrence();

    $excluded = calendarTagOccurrence([
        'id' => 'aaa-2026-02-12-100000',
        'start' => '2026-02-12T10:00:00+00:00',
        'end' => '2026-02-12T11:00:00+00:00',
        'is_recurring' => true,
        'recurrence_description' => 'weekly',
        'url' => '',
        'is_excluded' => true,
        'replacement_date' => '2026-02-19T00:00:00+00:00',
    ]);

    $this->tagOccurrences = collect([$visible]);

    $mock = Mockery::mock(OccurrenceCache::class);
    // The tag forwards $includeExcluded through to the cache — mirror that
    // boundary so tests can verify the param is actually propagated. Build
    // fresh collections each call so the mocked returns can't alias.
    $mock->shouldReceive('rebuild')->zeroOrMoreTimes();
    $mock->shouldReceive('all')->with(false)->andReturnUsing(fn () => collect([$visible]));
    $mock->shouldReceive('all')->with(true)->andReturnUsing(fn () => collect([$visible, $excluded]));
    $mock->shouldReceive('all')->withNoArgs()->andReturnUsing(fn () => collect([$visible]));
    $this->app->instance(OccurrenceCache::class, $mock);
});

afterEach(fn () => Carbon::setTestNow());

function calendarTag(array $params = [], array $context = [], ?OccurrenceResolver $resolver = null): Calendar
{
    $tag = $resolver ? new Calendar($resolver) : app(Calendar::class);
    $tag->setProperties([
        'parser' => app(Parser::class),
        'content' => '',
        'context' => $context,
        'params' => $params,
        'tag' => 'calendar',
        'tag_method' => 'index',
    ]);

    return $tag;
}

function calendarTagOccurrence(array $overrides = []): OccurrenceData
{
    return OccurrenceData::fromArray(array_merge([
        'id' => 'aaa-2026-02-05-100000',
        'entry_id' => 'aaa',
        'title' => 'Event A',
        'slug' => 'event-a',
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-02-05T10:00:00+00:00',
        'end' => '2026-02-05T11:00:00+00:00',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/event-a',
    ], $overrides));
}

test('loop items expose composed occurrence_id alongside entry id', function () {
    $result = calendarTag(['from' => '2026-02-01'])->index();

    $item = collect($result)->first();

    expect($item['id'])->toBe('aaa');
    expect($item['occurrence_id'])->toBe('aaa-2026-02-05-100000');
});

test('ics_download_url uses context occurrence_id when present', function () {
    $tag = calendarTag(
        params: [],
        context: ['id' => 'aaa', 'occurrence_id' => 'aaa-2026-02-05-100000']
    );

    expect($tag->icsDownloadUrl())->toContain('aaa-2026-02-05-100000');
});

test('loop items surface extras contributed by OccurrenceBuilding listeners', function () {
    $this->tagOccurrences = collect([
        calendarTagOccurrence([
            'image' => ['url' => '/assets/a.jpg'],
            'category' => 'music',
        ]),
    ]);
    $mock = Mockery::mock(OccurrenceCache::class);
    $mock->shouldReceive('all')->andReturn($this->tagOccurrences);
    $this->app->instance(OccurrenceCache::class, $mock);

    $item = collect(calendarTag(['from' => '2026-02-01'])->index())->first();

    expect($item['image'])->toBe(['url' => '/assets/a.jpg'])
        ->and($item['category'])->toBe('music')
        ->and($item['title'])->toBe('Event A');
});

test('ics_download_url falls back to context id when occurrence_id absent', function () {
    $tag = calendarTag(
        params: [],
        context: ['id' => 'legacy-id']
    );

    expect($tag->icsDownloadUrl())->toContain('legacy-id');
});

test('index hides excluded occurrences by default', function () {
    $result = calendarTag(['from' => '2026-02-01'])->index();

    expect($result)->toHaveCount(1);
    expect(collect($result)->first()['is_excluded'])->toBeFalse();
});

function occurrenceTagEntry(bool $published = true): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('published')->andReturn($published);
    $entry->shouldReceive('id')->andReturn('entry-id');
    $entry->shouldReceive('url')->andReturn('/events/event');

    return $entry;
}

function resolvedTagOccurrence(Entry $entry, string $date): ElSchneider\StatamicCalendar\Occurrences\Occurrence
{
    return new ElSchneider\StatamicCalendar\Occurrences\Occurrence(
        entry: $entry,
        start: Carbon::parse($date.' 10:00:00'),
        end: Carbon::parse($date.' 11:00:00'),
        isAllDay: false,
        isRecurring: true,
        recurrenceDescription: 'weekly',
    );
}

test('occurrence resolves the requested query-string date', function () {
    config()->set('statamic-calendar.url.strategy', 'query_string');
    request()->query->set('date', '2026-02-12');

    $entry = occurrenceTagEntry();
    EntryFacade::shouldReceive('find')->with('entry-id')->andReturn($entry);
    $occurrence = resolvedTagOccurrence($entry, '2026-02-12');
    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('findOccurrenceOnDate')->withArgs(fn ($foundEntry, Carbon $date) => $foundEntry === $entry && $date->toDateString() === '2026-02-12')->andReturn($occurrence);
    $resolver->shouldNotReceive('representative');

    $result = calendarTag(context: ['id' => 'entry-id'], resolver: $resolver)->occurrence();

    expect($result)->toHaveCount(1)
        ->and(array_keys($result[0]))->toBe(['occurrence_id', 'start', 'end', 'is_all_day', 'is_recurring', 'recurrence_description', 'url', 'is_excluded', 'replacement_date', 'replaces_date'])
        ->and($result[0]['start']->toDateString())->toBe('2026-02-12');
});

test('occurrence falls back to representative when the requested date does not resolve', function () {
    config()->set('statamic-calendar.url.strategy', 'query_string');
    request()->query->set('date', '2026-02-12');

    $entry = occurrenceTagEntry();
    EntryFacade::shouldReceive('find')->with('entry-id')->andReturn($entry);
    $occurrence = resolvedTagOccurrence($entry, '2026-02-19');
    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('findOccurrenceOnDate')->andReturnNull();
    $resolver->shouldReceive('representative')->with($entry)->andReturn($occurrence);

    expect(calendarTag(context: ['id' => 'entry-id'], resolver: $resolver)->occurrence()[0]['start']->toDateString())
        ->toBe('2026-02-19');
});

test('occurrence resolves the next upcoming occurrence without a date parameter', function () {
    config()->set('statamic-calendar.url.strategy', 'query_string');

    $entry = occurrenceTagEntry();
    EntryFacade::shouldReceive('find')->with('entry-id')->andReturn($entry);
    $occurrence = resolvedTagOccurrence($entry, '2026-02-05');
    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('representative')->with($entry)->andReturn($occurrence);

    $result = calendarTag(params: ['entry' => 'entry-id'], resolver: $resolver)->occurrence();

    expect($result[0]['start']->toDateString())->toBe('2026-02-05');
});

test('occurrence resolves the most recent past occurrence when representative has no upcoming date', function () {
    $entry = occurrenceTagEntry();
    EntryFacade::shouldReceive('find')->with('entry-id')->andReturn($entry);
    $occurrence = resolvedTagOccurrence($entry, '2026-01-10');
    $resolver = Mockery::mock(OccurrenceResolver::class);
    $resolver->shouldReceive('representative')->with($entry)->andReturn($occurrence);

    $result = calendarTag(context: ['id' => 'entry-id'], resolver: $resolver)->occurrence();

    expect($result[0]['start']->toDateString())->toBe('2026-01-10');
});

test('occurrence returns no items for missing or unpublished entries', function () {
    EntryFacade::shouldReceive('find')->with('missing')->andReturnNull();
    $resolver = Mockery::mock(OccurrenceResolver::class);

    expect(calendarTag(context: ['id' => 'missing'], resolver: $resolver)->occurrence())->toBe([]);

    $entry = occurrenceTagEntry(published: false);
    EntryFacade::shouldReceive('find')->with('unpublished')->andReturn($entry);

    expect(calendarTag(context: ['id' => 'unpublished'], resolver: $resolver)->occurrence())->toBe([]);
});

test('include_excluded surfaces excluded occurrences with metadata', function () {
    $result = calendarTag(['from' => '2026-02-01', 'include_excluded' => true])->index();

    expect($result)->toHaveCount(2);

    $excluded = collect($result)->firstWhere('is_excluded', true);
    expect($excluded)->not->toBeNull();
    expect($excluded['replacement_date']?->format('Y-m-d'))->toBe('2026-02-19');
    expect($excluded['url'])->toBe('');
});
