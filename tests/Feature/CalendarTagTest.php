<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Tags\Calendar;
use Statamic\Contracts\View\Antlers\Parser;

beforeEach(function () {
    Carbon::setTestNow('2026-02-01 00:00:00');

    $visible = OccurrenceData::fromArray([
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
    ]);

    $excluded = OccurrenceData::fromArray([
        'id' => 'aaa-2026-02-12-100000',
        'entry_id' => 'aaa',
        'title' => 'Event A',
        'slug' => 'event-a',
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-02-12T10:00:00+00:00',
        'end' => '2026-02-12T11:00:00+00:00',
        'is_all_day' => false,
        'is_recurring' => true,
        'recurrence_description' => 'weekly',
        'url' => '',
        'is_excluded' => true,
        'replacement_date' => '2026-02-19T00:00:00+00:00',
        'replaces_date' => null,
    ]);

    $mock = Mockery::mock(OccurrenceCache::class);
    // The tag forwards $includeExcluded through to the cache — mirror that
    // boundary so tests can verify the param is actually propagated. Build
    // fresh collections each call so the mocked returns can't alias.
    $mock->shouldReceive('all')->with(false)->andReturnUsing(fn () => collect([$visible]));
    $mock->shouldReceive('all')->with(true)->andReturnUsing(fn () => collect([$visible, $excluded]));
    $mock->shouldReceive('all')->withNoArgs()->andReturnUsing(fn () => collect([$visible]));
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

test('include_excluded surfaces excluded occurrences with metadata', function () {
    $result = calendarTag(['from' => '2026-02-01', 'include_excluded' => true])->index();

    expect($result)->toHaveCount(2);

    $excluded = collect($result)->firstWhere('is_excluded', true);
    expect($excluded)->not->toBeNull();
    expect($excluded['replacement_date']?->format('Y-m-d'))->toBe('2026-02-19');
    expect($excluded['url'])->toBe('');
});
