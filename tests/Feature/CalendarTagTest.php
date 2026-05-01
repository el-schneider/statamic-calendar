<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Tags\Calendar;
use Statamic\Contracts\View\Antlers\Parser;

beforeEach(function () {
    Carbon::setTestNow('2026-02-01 00:00:00');

    $this->tagOccurrences = collect([
        calendarTagOccurrence(),
    ]);

    $mock = Mockery::mock(OccurrenceCache::class);
    $mock->shouldReceive('all')->andReturn($this->tagOccurrences);
    $this->app->instance(OccurrenceCache::class, $mock);
});

afterEach(fn () => Carbon::setTestNow());

function calendarTag(array $params = [], array $context = []): Calendar
{
    $tag = app(Calendar::class);
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
