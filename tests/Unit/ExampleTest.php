<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;

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
