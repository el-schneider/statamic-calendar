<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\GraphQL\Types\CalendarOccurrenceType;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceWindow;
use ElSchneider\StatamicCalendar\Tests\GraphQL\TestVenueType;
use GraphQL\Type\Definition\FieldDefinition;
use Statamic\Facades\GraphQL;

beforeEach(function () {
    Carbon::setTestNow('2026-03-01 10:00:00');

    $this->calendarOccurrences = collect([
        makeGraphqlOccurrence(['id' => 'past-2026-02-28-100000', 'entry_id' => 'past', 'title' => 'Past event', 'start' => '2026-02-28T10:00:00+00:00', 'end' => '2026-02-28T11:00:00+00:00']),
        makeGraphqlOccurrence(['id' => 'ongoing-2026-03-01-090000', 'entry_id' => 'ongoing', 'title' => 'Ongoing event', 'start' => '2026-03-01T09:00:00+00:00', 'end' => '2026-03-01T11:00:00+00:00']),
        makeGraphqlOccurrence(['id' => 'yoga-2026-03-05-100000', 'entry_id' => 'yoga', 'title' => 'Yoga Class', 'tags' => ['fitness', 'wellness'], 'organizer_id' => 'org-1', 'start' => '2026-03-05T10:00:00+00:00', 'end' => '2026-03-05T11:00:00+00:00']),
        makeGraphqlOccurrence(['id' => 'meetup-2026-03-10-140000', 'entry_id' => 'meetup', 'title' => 'Laravel Meetup', 'tags' => ['tech'], 'organizer_id' => 'org-2', 'start' => '2026-03-10T14:00:00+00:00', 'end' => null]),
        makeGraphqlOccurrence(['id' => 'rescheduled-2026-03-22-100000', 'entry_id' => 'rescheduled', 'title' => 'Rescheduled event', 'start' => '2026-03-22T10:00:00+00:00', 'end' => '2026-03-22T11:00:00+00:00', 'replaces_date' => '2026-03-15T10:00:00+00:00']),
    ]);
    $this->excludedOccurrence = makeGraphqlOccurrence(['id' => 'cancelled-2026-03-15-100000', 'entry_id' => 'cancelled', 'title' => 'Cancelled event', 'start' => '2026-03-15T10:00:00+00:00', 'end' => '2026-03-15T11:00:00+00:00', 'is_excluded' => true, 'replacement_date' => '2026-03-22T10:00:00+00:00']);

    $cache = Mockery::mock(OccurrenceCache::class);
    $cache->shouldReceive('all')->with(false)->andReturnUsing(fn () => $this->calendarOccurrences->values());
    $cache->shouldReceive('all')->with(true)->andReturnUsing(fn () => $this->calendarOccurrences->concat([$this->excludedOccurrence])->values());
    $cache->shouldReceive('status')->andReturnUsing(function (array $statuses, Carbon $now, bool $includeExcluded = false) {
        $occurrences = $includeExcluded ? $this->calendarOccurrences->concat([$this->excludedOccurrence]) : $this->calendarOccurrences;

        return $occurrences->filter(fn (OccurrenceData $occurrence) => OccurrenceWindow::hasStatus($occurrence, $statuses, $now))->values();
    });
    $this->app->instance(OccurrenceCache::class, $cache);
});

afterEach(fn () => Carbon::setTestNow());

function makeGraphqlOccurrence(array $overrides = []): OccurrenceData
{
    return OccurrenceData::fromArray(array_merge([
        'id' => 'test-2026-03-01-100000', 'entry_id' => 'test', 'title' => 'Test event', 'slug' => 'test-event',
        'teaser' => null, 'organizer_id' => null, 'organizer_slug' => null, 'organizer_title' => null, 'organizer_url' => null,
        'tags' => [], 'start' => '2026-03-01T10:00:00+00:00', 'end' => '2026-03-01T11:00:00+00:00',
        'is_all_day' => false, 'is_recurring' => false, 'recurrence_description' => null, 'url' => '/events/test-event',
        'is_excluded' => false, 'replacement_date' => null, 'replaces_date' => null,
    ], $overrides));
}

function graphql(string $query, array $variables = []): Illuminate\Testing\TestResponse
{
    return test()->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
}

test('serves occurrences when collection GraphQL resources are disabled', function () {
    config(['statamic.graphql.resources.collections' => false]);

    graphql('{ calendarOccurrences { total } }')
        ->assertOk()
        ->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.total', 4);
});

test('serves typed occurrence core fields through the native GraphQL endpoint', function () {
    graphql('{ calendarOccurrences { data { id entry_id title slug teaser organizer_id organizer_slug organizer_title organizer_url tags start end is_all_day is_recurring recurrence_description url is_excluded replacement_date replaces_date } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.data.0.id', 'ongoing-2026-03-01-090000')
        ->assertJsonPath('data.calendarOccurrences.data.0.organizer_id', null)
        ->assertJsonPath('data.calendarOccurrences.data.0.start', '2026-03-01T09:00:00+00:00')
        ->assertJsonPath('data.calendarOccurrences.data.0.tags', []);
});

test('defaults to overlapping occurrences from now in start order', function () {
    graphql('{ calendarOccurrences { data { title } total per_page current_page } nullable: calendarOccurrences(limit: null, page: null, include_excluded: null, from: null, status: null) { total per_page current_page } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.total', 4)
        ->assertJsonPath('data.calendarOccurrences.per_page', 15)
        ->assertJsonPath('data.nullable.total', 4)
        ->assertJsonPath('data.calendarOccurrences.data.0.title', 'Ongoing event');
});

test('combines window tag and organizer filters', function () {
    graphql('{ calendarOccurrences(from: "2026-03-01", to: "2026-03-10T14:00:00Z", tags: ["wellness", "tech"], organizer: "org-2") { data { id } total } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.total', 1)
        ->assertJsonPath('data.calendarOccurrences.data.0.id', 'meetup-2026-03-10-140000');
});

test('sorts multiple occurrences in descending start order', function () {
    graphql('{ calendarOccurrences(from: "2026-03-01", to: "2026-03-10T14:00:00Z", tags: ["wellness", "tech"], sort: desc) { data { id } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.data.0.id', 'meetup-2026-03-10-140000')
        ->assertJsonPath('data.calendarOccurrences.data.1.id', 'yoga-2026-03-05-100000');
});

test('uses inclusive overlap for all-day and no-end occurrences', function () {
    $this->calendarOccurrences->push(makeGraphqlOccurrence(['id' => 'all-day-2026-02-28-000000', 'title' => 'All day', 'start' => '2026-02-28T00:00:00+00:00', 'end' => '2026-03-01T00:00:00+00:00', 'is_all_day' => true]));

    graphql('{ calendarOccurrences(from: "2026-03-01T10:00:00Z", to: "2026-03-10T14:00:00Z") { data { id } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonFragment(['id' => 'all-day-2026-02-28-000000'])
        ->assertJsonFragment(['id' => 'meetup-2026-03-10-140000']);
});

test('filters statuses without the default from window', function () {
    graphql('{ calendarOccurrences(status: [past, ongoing]) { data { id } total } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.total', 2)
        ->assertJsonPath('data.calendarOccurrences.data.0.id', 'past-2026-02-28-100000');
});

test('hides exclusions unless requested and keeps rescheduled occurrences', function () {
    $response = graphql('{ hidden: calendarOccurrences { data { id } } visible: calendarOccurrences(include_excluded: true) { data { id is_excluded replacement_date replaces_date } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonFragment(['id' => 'rescheduled-2026-03-22-100000'])
        ->assertJsonFragment(['id' => 'cancelled-2026-03-15-100000', 'is_excluded' => true])
        ->assertJsonPath('data.visible.data.3.replacement_date', '2026-03-22T10:00:00+00:00')
        ->assertJsonPath('data.visible.data.4.replaces_date', '2026-03-15T10:00:00+00:00');

    expect(collect($response->json('data.hidden.data'))->pluck('id'))->not->toContain('cancelled-2026-03-15-100000');
});

test('paginates each root alias, caps limits, and returns empty pages', function () {
    config(['statamic-calendar.graphql.max_per_page' => 2]);

    $response = graphql('{ first: calendarOccurrences(limit: 999) { total per_page } empty: calendarOccurrences(page: 9, sort: desc) { data { id } total current_page } }');
    $response->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.first.per_page', 2)
        ->assertJsonPath('data.empty.total', 4)
        ->assertJsonPath('data.empty.current_page', 9)
        ->assertJsonCount(0, 'data.empty.data');
});

test('accepts null filter variables', function () {
    graphql('query ($from: String, $limit: Int, $page: Int) { calendarOccurrences(from: $from, limit: $limit, page: $page) { total } }', ['from' => null, 'limit' => null, 'page' => null])
        ->assertOk()->assertJsonMissingPath('errors')->assertJsonPath('data.calendarOccurrences.total', 4);
});

test('validates date filters', function () {
    graphql('query ($from: String) { calendarOccurrences(from: $from) { total } }', ['from' => 'not-a-date'])
        ->assertOk()->assertJsonPath('errors.0.message', 'validation');
});

test('validates limits', function () {
    graphql('{ calendarOccurrences(limit: 0) { total } }')
        ->assertOk()->assertJsonPath('errors.0.message', 'validation');
});

test('validates pages', function () {
    graphql('{ calendarOccurrences(page: -1) { total } }')
        ->assertOk()->assertJsonPath('errors.0.message', 'validation');
});

test('validates status enum values', function () {
    graphql('{ calendarOccurrences(status: [tomorrow]) { total } }')
        ->assertOk()->assertJsonPath('errors.0.message', 'Value "tomorrow" does not exist in "CalendarOccurrenceStatus" enum.');
});

test('matches REST ids for representative filters', function () {
    $rest = $this->getJson('/api/calendar/occurrences?from=2026-03-01&to=2026-03-10T14:00:00Z&tags=wellness,tech')->assertOk()->json('data');
    $graphql = graphql('{ calendarOccurrences(from: "2026-03-01", to: "2026-03-10T14:00:00Z", tags: ["wellness", "tech"]) { data { id } } }')
        ->assertOk()->assertJsonMissingPath('errors')->json('data.calendarOccurrences.data');

    expect(collect($graphql)->pluck('id')->all())->toBe(collect($rest)->pluck('id')->all());
});

test('accepts native field definitions for registered cached extras', function () {
    GraphQL::addField(CalendarOccurrenceType::NAME, 'attendance', fn () => new FieldDefinition([
        'name' => 'attendance',
        'type' => GraphQL::int(),
    ]));
    $this->calendarOccurrences->transform(fn (OccurrenceData $occurrence) => OccurrenceData::fromArray([
        ...$occurrence->toArray(), 'attendance' => 42,
    ]));

    graphql('{ calendarOccurrences { data { attendance } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.data.0.attendance', 42);
});

test('resolves registered scalar and nested extras and rejects unregistered extras', function () {
    GraphQL::addType(TestVenueType::class);
    GraphQL::addField(CalendarOccurrenceType::NAME, 'attendance', fn () => ['type' => GraphQL::nonNull(GraphQL::int()), 'resolve' => fn (array $occurrence) => $occurrence['attendance']]);
    GraphQL::addField(CalendarOccurrenceType::NAME, 'venue', fn () => ['type' => GraphQL::type(TestVenueType::NAME), 'resolve' => fn (array $occurrence) => $occurrence['venue']]);
    $this->calendarOccurrences->transform(fn (OccurrenceData $occurrence) => OccurrenceData::fromArray([...$occurrence->toArray(), 'attendance' => 42, 'venue' => ['name' => 'Town Hall'], 'private_note' => 'Not registered']));

    graphql('{ calendarOccurrences { data { attendance venue { name } } } }')
        ->assertOk()->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.data.0.attendance', 42)
        ->assertJsonPath('data.calendarOccurrences.data.0.venue.name', 'Town Hall');
    graphql('{ calendarOccurrences { data { private_note } } }')
        ->assertOk()->assertJsonPath('errors.0.message', 'Cannot query field "private_note" on type "CalendarOccurrence".');
});
