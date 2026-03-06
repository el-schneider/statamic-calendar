<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceCache;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Statamic\Facades\Antlers;

beforeEach(function () {
    Carbon::setTestNow('2026-02-01 00:00:00');

    $this->tagOccurrences = collect([
        makeTagOccurrence([
            'id' => 'aaa-2026-02-05-100000',
            'entry_id' => 'aaa',
            'title' => 'Event A',
            'slug' => 'event-a',
            'organizer_id' => 'org-1',
            'start' => '2026-02-05T10:00:00+00:00',
            'end' => '2026-02-05T11:00:00+00:00',
        ]),
        makeTagOccurrence([
            'id' => 'bbb-2026-02-10-100000',
            'entry_id' => 'bbb',
            'title' => 'Event B',
            'slug' => 'event-b',
            'organizer_id' => 'org-1',
            'start' => '2026-02-10T10:00:00+00:00',
            'end' => '2026-02-10T11:00:00+00:00',
        ]),
        makeTagOccurrence([
            'id' => 'ccc-2026-02-15-100000',
            'entry_id' => 'ccc',
            'title' => 'Event C',
            'slug' => 'event-c',
            'organizer_id' => 'org-2',
            'start' => '2026-02-15T10:00:00+00:00',
            'end' => '2026-02-15T11:00:00+00:00',
        ]),
        makeTagOccurrence([
            'id' => 'ddd-2026-02-20-100000',
            'entry_id' => 'ddd',
            'title' => 'Event D',
            'slug' => 'event-d',
            'organizer_id' => 'org-2',
            'start' => '2026-02-20T10:00:00+00:00',
            'end' => '2026-02-20T11:00:00+00:00',
        ]),
    ]);

    $orgOneOccurrences = $this->tagOccurrences->filter(fn (OccurrenceData $o) => $o->organizerId === 'org-1')->values();

    $mock = Mockery::mock(OccurrenceCache::class);
    $mock->shouldReceive('all')->andReturn($this->tagOccurrences);
    $mock->shouldReceive('forOrganizer')->with('org-1')->andReturn($orgOneOccurrences);
    $this->app->instance(OccurrenceCache::class, $mock);
});

afterEach(fn () => Carbon::setTestNow());

function makeTagOccurrence(array $overrides = []): OccurrenceData
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
    $template = '{{ calendar from="2026-02-01" }}{{ title }}|{{ /calendar }}';
    $output = (string) Antlers::parse($template, []);

    expect($output)->toContain('Event A');
    expect($output)->toContain('Event B');
    expect($output)->toContain('Event C');
    expect($output)->toContain('Event D');
});

test('index paginates when paginate param is set', function () {
    $template = '{{ calendar from="2026-02-01" paginate="2" as="items" }}{{ items }}{{ title }}|{{ /items }}TOTAL:{{ paginate:total_items }}{{ /calendar }}';
    $output = (string) Antlers::parse($template, []);

    // Only 2 items on page 1
    expect($output)->toContain('Event A');
    expect($output)->toContain('Event B');
    expect($output)->not->toContain('Event C');
    expect($output)->not->toContain('Event D');
    expect($output)->toContain('TOTAL:4');
});

test('index pagination respects page query param', function () {
    request()->merge(['page' => 2]);

    $template = '{{ calendar from="2026-02-01" paginate="2" as="items" }}{{ items }}{{ title }}|{{ /items }}TOTAL:{{ paginate:total_items }}{{ /calendar }}';
    $output = (string) Antlers::parse($template, []);

    // Page 2 should show items 3 and 4
    expect($output)->not->toContain('Event A');
    expect($output)->not->toContain('Event B');
    expect($output)->toContain('Event C');
    expect($output)->toContain('Event D');
    expect($output)->toContain('TOTAL:4');
});

test('for_organizer paginates', function () {
    $template = '{{ calendar:for_organizer organizer="org-1" from="2026-02-01" paginate="1" as="items" }}{{ items }}{{ title }}{{ /items }}TOTAL:{{ paginate:total_items }}{{ /calendar:for_organizer }}';
    $output = (string) Antlers::parse($template, []);

    // org-1 has Event A and Event B; paginate=1 shows only first page
    expect($output)->toContain('TOTAL:2');
    expect($output)->toContain('Event A');
    expect($output)->not->toContain('Event B');
});
