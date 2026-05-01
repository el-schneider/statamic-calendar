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
        paginationTagOccurrence([
            'id' => 'aaa-2026-02-05-100000',
            'entry_id' => 'aaa',
            'title' => 'Event A',
            'slug' => 'event-a',
            'organizer_id' => 'org-1',
            'start' => '2026-02-05T10:00:00+00:00',
            'end' => '2026-02-05T11:00:00+00:00',
        ]),
        paginationTagOccurrence([
            'id' => 'bbb-2026-02-10-100000',
            'entry_id' => 'bbb',
            'title' => 'Event B',
            'slug' => 'event-b',
            'organizer_id' => 'org-1',
            'start' => '2026-02-10T10:00:00+00:00',
            'end' => '2026-02-10T11:00:00+00:00',
        ]),
        paginationTagOccurrence([
            'id' => 'ccc-2026-02-15-100000',
            'entry_id' => 'ccc',
            'title' => 'Event C',
            'slug' => 'event-c',
            'organizer_id' => 'org-2',
            'start' => '2026-02-15T10:00:00+00:00',
            'end' => '2026-02-15T11:00:00+00:00',
        ]),
        paginationTagOccurrence([
            'id' => 'ddd-2026-02-20-100000',
            'entry_id' => 'ddd',
            'title' => 'Event D',
            'slug' => 'event-d',
            'organizer_id' => 'org-2',
            'start' => '2026-02-20T10:00:00+00:00',
            'end' => '2026-02-20T11:00:00+00:00',
        ]),
    ]);

    $orgOneOccurrences = $this->tagOccurrences
        ->filter(fn (OccurrenceData $o) => $o->organizerId === 'org-1')
        ->values();

    $mock = Mockery::mock(OccurrenceCache::class);
    $mock->shouldReceive('all')->andReturn($this->tagOccurrences);
    $mock->shouldReceive('forOrganizer')->with('org-1')->andReturn($orgOneOccurrences);
    $this->app->instance(OccurrenceCache::class, $mock);
});

afterEach(fn () => Carbon::setTestNow());

function paginationCalendarTag(array $params = [], string $content = ''): Calendar
{
    $tag = app(Calendar::class);
    $tag->setProperties([
        'parser' => app(Parser::class),
        'content' => $content,
        'context' => [],
        'params' => $params,
        'tag' => 'calendar',
        'tag_method' => 'index',
    ]);

    return $tag;
}

function paginationTagOccurrence(array $overrides = []): OccurrenceData
{
    return OccurrenceData::fromArray(array_merge([
        'id' => 'tag-test-2026-02-01-100000',
        'entry_id' => 'tag-test',
        'title' => 'Tag Test Event',
        'slug' => 'tag-test-event',
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-02-01T10:00:00+00:00',
        'end' => '2026-02-01T11:00:00+00:00',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/tag-test-event',
    ], $overrides));
}

test('index returns all items without paginate param', function () {
    $result = paginationCalendarTag(['from' => '2026-02-01'])->index();

    expect($result)->toBeArray()->toHaveCount(4);

    $titles = collect($result)->pluck('title')->all();
    expect($titles)->toBe(['Event A', 'Event B', 'Event C', 'Event D']);
});

test('index supports as param without pagination', function () {
    $result = paginationCalendarTag(['from' => '2026-02-01', 'as' => 'events'])->index();

    expect($result)->toHaveKey('events');
    expect($result)->toHaveKey('total_results');
    expect($result)->toHaveKey('no_results');
    expect($result['total_results'])->toBe(4);
    expect($result['no_results'])->toBeFalse();
});

test('index paginates when paginate param is set', function () {
    $result = paginationCalendarTag(['from' => '2026-02-01', 'paginate' => '2', 'as' => 'items'])->index();

    expect($result)->toHaveKey('items');
    expect($result)->toHaveKey('paginate');
    expect($result['items'])->toHaveCount(2);

    $titles = $result['items']->pluck('title')->all();
    expect($titles)->toBe(['Event A', 'Event B']);
    expect($result['paginate']['total_items'])->toBe(4);
    expect($result['paginate']['total_pages'])->toBe(2);
});

test('index pagination respects page query param', function () {
    request()->merge(['page' => 2]);

    $result = paginationCalendarTag(['from' => '2026-02-01', 'paginate' => '2', 'as' => 'items'])->index();

    $titles = $result['items']->pluck('title')->all();
    expect($titles)->toBe(['Event C', 'Event D']);
    expect($result['paginate']['total_items'])->toBe(4);
});

test('index pagination clamps invalid page to first page', function () {
    request()->merge(['page' => -1]);

    $result = paginationCalendarTag(['from' => '2026-02-01', 'paginate' => '2', 'as' => 'items'])->index();

    $titles = $result['items']->pluck('title')->all();
    expect($titles)->toBe(['Event A', 'Event B']);
    expect($result['paginate']['current_page'])->toBe(1);
});

test('for_organizer paginates', function () {
    $tag = paginationCalendarTag(['organizer' => 'org-1', 'from' => '2026-02-01', 'paginate' => '1', 'as' => 'items']);
    $result = $tag->forOrganizer();

    expect($result)->toHaveKey('items');
    expect($result)->toHaveKey('paginate');
    expect($result['items'])->toHaveCount(1);
    expect($result['items']->first()['title'])->toBe('Event A');
    expect($result['paginate']['total_items'])->toBe(2);
});
