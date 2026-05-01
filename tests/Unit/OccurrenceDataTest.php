<?php

declare(strict_types=1);

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;

test('composeId formats as {entry_id}-{Y-m-d-His} and coerces int ids', function () {
    $start = Carbon::parse('2026-03-06 15:00:00');

    expect(OccurrenceData::composeId('abc-123', $start))->toBe('abc-123-2026-03-06-150000');
    expect(OccurrenceData::composeId(42, $start))->toBe('42-2026-03-06-150000');
});

test('OccurrenceData can be created from array and serialized back', function () {
    $data = [
        'id' => 'abc-123-2026-03-06-150000',
        'entry_id' => 'abc-123',
        'title' => 'Test Event',
        'slug' => 'test-event',
        'teaser' => 'A test',
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => ['music'],
        'start' => '2026-03-06T15:00:00+00:00',
        'end' => '2026-03-06T16:00:00+00:00',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/test-event',
    ];

    $occurrence = OccurrenceData::fromArray($data);
    $array = $occurrence->toArray();

    expect($occurrence->title)->toBe('Test Event');
    expect($occurrence->slug)->toBe('test-event');
    expect($occurrence->tags)->toBe(['music']);
    expect($array['id'])->toBe('abc-123-2026-03-06-150000');
});

test('fromArray collects non-reserved keys into $extra and toArray splats them', function () {
    $occurrence = OccurrenceData::fromArray([...baseOccurrenceArray(), 'image' => 'foo.jpg', 'category' => 'music']);

    expect($occurrence->extra)->toBe(['image' => 'foo.jpg', 'category' => 'music'])
        ->and($occurrence->toArray()['image'])->toBe('foo.jpg');
});

test('fromArray strips reserved keys from $extra so core fields cannot be shadowed', function () {
    $occurrence = OccurrenceData::fromArray([
        ...baseOccurrenceArray(),
        'title' => 'Real title',
        'image' => 'ok',
    ]);

    expect($occurrence->extra)->toBe(['image' => 'ok'])
        ->and($occurrence->toArray()['title'])->toBe('Real title');
});

function baseOccurrenceArray(): array
{
    return [
        'id' => 'abc-2026-03-06-150000',
        'entry_id' => 'abc',
        'title' => 'Base',
        'slug' => 'base',
        'teaser' => null,
        'organizer_id' => null,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-03-06T15:00:00+00:00',
        'end' => null,
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/base',
    ];
}

test('OccurrenceData normalizes numeric ids to strings', function () {
    $occurrence = OccurrenceData::fromArray([
        'id' => '1-2026-03-06-150000',
        'entry_id' => 1,
        'title' => 'Numeric IDs',
        'slug' => 'numeric-ids',
        'teaser' => null,
        'organizer_id' => 2,
        'organizer_slug' => null,
        'organizer_title' => null,
        'organizer_url' => null,
        'tags' => [],
        'start' => '2026-03-06T15:00:00+00:00',
        'end' => null,
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_description' => null,
        'url' => '/events/numeric-ids',
    ]);

    expect($occurrence->entryId)->toBe('1')
        ->and($occurrence->organizerId)->toBe('2');
});
