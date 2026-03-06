<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;

beforeEach(function () {
    Carbon::setTestNow('2026-03-01 10:00:00');

    $this->occurrences = collect([
        makeApiOccurrence([
            'id' => 'aaa-2026-03-05-100000',
            'entry_id' => 'aaa',
            'title' => 'Yoga Class',
            'slug' => 'yoga-class',
            'tags' => ['fitness', 'wellness'],
            'organizer_id' => 'org-1',
            'start' => '2026-03-05T10:00:00+00:00',
            'end' => '2026-03-05T11:00:00+00:00',
        ]),
        makeApiOccurrence([
            'id' => 'bbb-2026-03-10-140000',
            'entry_id' => 'bbb',
            'title' => 'Laravel Meetup',
            'slug' => 'laravel-meetup',
            'tags' => ['tech'],
            'organizer_id' => 'org-2',
            'start' => '2026-03-10T14:00:00+00:00',
            'end' => '2026-03-10T16:00:00+00:00',
        ]),
        makeApiOccurrence([
            'id' => 'ccc-2026-03-20-090000',
            'entry_id' => 'ccc',
            'title' => 'Art Workshop',
            'slug' => 'art-workshop',
            'tags' => ['art', 'wellness'],
            'organizer_id' => 'org-1',
            'start' => '2026-03-20T09:00:00+00:00',
            'end' => '2026-03-20T12:00:00+00:00',
        ]),
        makeApiOccurrence([
            'id' => 'ddd-2026-02-15-180000',
            'entry_id' => 'ddd',
            'title' => 'Past Event',
            'slug' => 'past-event',
            'tags' => [],
            'start' => '2026-02-15T18:00:00+00:00',
            'end' => '2026-02-15T20:00:00+00:00',
        ]),
    ]);

    $mock = Mockery::mock(OccurrenceCache::class);
    $mock->shouldReceive('all')->andReturn($this->occurrences);
    $this->app->instance(OccurrenceCache::class, $mock);
});

afterEach(fn () => Carbon::setTestNow());

function makeApiOccurrence(array $overrides = []): OccurrenceData
{
    return OccurrenceData::fromArray(array_merge([
        'id' => 'test-2026-03-01-100000',
        'entry_id' => 'test',
        'title' => 'Test Event',
        'slug' => 'test-event',
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-03-01T10:00:00+00:00',
        'end' => '2026-03-01T11:00:00+00:00',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/test-event',
    ], $overrides));
}

test('returns occurrences from now by default', function () {
    $this->getJson('/api/calendar/occurrences')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.title', 'Yoga Class')
        ->assertJsonPath('data.1.title', 'Laravel Meetup')
        ->assertJsonPath('data.2.title', 'Art Workshop');
});

test('filters by from and to', function () {
    $this->getJson('/api/calendar/occurrences?from=2026-03-08&to=2026-03-15')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Laravel Meetup');
});

test('includes past occurrences when from is in the past', function () {
    $this->getJson('/api/calendar/occurrences?from=2026-02-01')
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

test('limits results', function () {
    $this->getJson('/api/calendar/occurrences?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('sorts descending', function () {
    $this->getJson('/api/calendar/occurrences?sort=desc')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Art Workshop')
        ->assertJsonPath('data.2.title', 'Yoga Class');
});

test('filters by tags', function () {
    $this->getJson('/api/calendar/occurrences?tags=wellness')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Yoga Class')
        ->assertJsonPath('data.1.title', 'Art Workshop');
});

test('filters by multiple tags (comma-separated)', function () {
    $this->getJson('/api/calendar/occurrences?tags=tech,art')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('filters by organizer', function () {
    $this->getJson('/api/calendar/occurrences?organizer=org-2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Laravel Meetup');
});

test('combines filters', function () {
    $this->getJson('/api/calendar/occurrences?tags=wellness&organizer=org-1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('returns empty array when no occurrences match', function () {
    $this->getJson('/api/calendar/occurrences?tags=nonexistent')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('response contains expected fields', function () {
    $this->getJson('/api/calendar/occurrences?limit=1')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'entry_id',
                    'title',
                    'slug',
                    'teaser',
                    'organizer_id',
                    'tags',
                    'start',
                    'end',
                    'is_all_day',
                    'is_recurring',
                    'recurrence_description',
                    'url',
                ],
            ],
        ]);
});

test('returns 404 when api is disabled', function () {
    $this->getJson('/api/calendar/occurrences-that-does-not-exist')
        ->assertNotFound();
});

// --- Pagination ---

test('paginates with page and per_page params', function () {
    $this->getJson('/api/calendar/occurrences?from=2026-02-01&page=1&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('total', 4)
        ->assertJsonPath('current_page', 1)
        ->assertJsonPath('last_page', 2);
});

test('returns second page', function () {
    $response = $this->getJson('/api/calendar/occurrences?from=2026-02-01&page=2&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Sorted asc: Past Event (02-15), Yoga Class (03-05), Laravel Meetup (03-10), Art Workshop (03-20)
    // Page 2 = items 3 and 4
    $response->assertJsonPath('data.0.title', 'Laravel Meetup');
});

test('caps per_page at max_per_page config', function () {
    config(['statamic-calendar.api.max_per_page' => 2]);

    $this->getJson('/api/calendar/occurrences?from=2026-02-01&page=1&per_page=999')
        ->assertOk()
        ->assertJsonPath('per_page', 2);
});

test('returns unpaginated response when page param absent', function () {
    $response = $this->getJson('/api/calendar/occurrences?from=2026-02-01')
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('data');
    expect($data)->not->toHaveKey('total');
});

test('combines pagination with filters', function () {
    $this->getJson('/api/calendar/occurrences?tags=wellness&page=1&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('total', 2);
});
