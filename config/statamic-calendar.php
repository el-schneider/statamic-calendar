<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Event Source
    |--------------------------------------------------------------------------
    |
    | The Statamic collection handle that contains your event entries.
    |
    */

    'collection' => 'events',

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Configure how the addon reads dates, taxonomy tags, and the organizer
    | relation from an event entry.
    |
    */

    'fields' => [
        /*
        | The grid field handle on event entries that contains the dates.
        | Sub-field handles (start_date, start_time, etc.) are fixed —
        | use the provided example blueprint as a starting point.
        */
        'dates' => 'dates',

        'teaser' => 'teaser',
        'teaser_fallback' => 'description',

        'tags' => [
            'handle' => 'event_tags',
        ],

        /*
        | A relationship field on the event entry (eg. entries field).
        | If null, organizer data won't be cached.
        */
        'organizer' => [
            'handle' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Occurrence URLs
    |--------------------------------------------------------------------------
    |
    | date_segments: /{prefix}/{Y}/{m}/{d}/{slug}
    | query_string:  {entry_url}?{param}=YYYY-MM-DD
    |
    */

    'url' => [
        'strategy' => 'query_string',

        'query_string' => [
            'param' => 'date',
            'format' => 'Y-m-d',
        ],

        'date_segments' => [
            'prefix' => 'events',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Occurrences are materialized into Laravel's cache store for fast listing.
    |
    */

    'cache' => [
        'key' => 'statamic_calendar.occurrences',
        'days_ahead' => 365,
    ],
];
