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
        'dates' => [
            'handle' => 'dates',
            'keys' => [
                'start_date' => 'start_date',
                'start_time' => 'start_time',
                'end_date' => 'end_date',
                'end_time' => 'end_time',
                'is_all_day' => 'is_all_day',

                'is_recurring' => 'is_recurring',
                'frequency' => 'frequency',
                'interval' => 'interval',

                'weekdays' => 'weekdays',

                'monthly_type' => 'monthly_type',
                'monthday' => 'monthday',
                'weekday_ordinal' => 'weekday_ordinal',
                'weekday' => 'weekday',

                'recurrence_end' => 'recurrence_end',
                'count' => 'count',
                'until' => 'until',

                'exclusions' => 'exclusions',
                'additions' => 'additions',
            ],
        ],

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
        'strategy' => 'date_segments',

        'date_segments' => [
            'prefix' => 'events',
        ],

        'query_string' => [
            'param' => 'date',
            'format' => 'Y-m-d',
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
