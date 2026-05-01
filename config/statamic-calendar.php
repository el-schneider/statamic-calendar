<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Source Collection
    |--------------------------------------------------------------------------
    |
    | The Statamic collection handle that contains your calendar entries.
    |
    */

    'collection' => 'events',

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Configure how the addon reads dates, taxonomy tags, and the organizer
    | relation from an entry.
    |
    */

    'fields' => [
        /*
        | The grid field handle on entries that contains the dates.
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
        | A relationship field on the entry (eg. entries field).
        | If null, organizer data won't be cached.
        */
        'organizer' => [
            'handle' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The addon can automatically register a front-end route for the calendar
    | index page. Set to false to disable and wire your own route instead.
    |
    | The template is resolved as "statamic-calendar/index" — override it by
    | publishing views or placing your own in resources/views/statamic-calendar/.
    |
    */

    'routes' => [
        'index' => '/calendar',
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
            'prefix' => 'calendar',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | iCalendar (.ics) Export
    |--------------------------------------------------------------------------
    |
    | Expose an .ics feed that calendar apps (Apple Calendar, Google Calendar,
    | Outlook) can subscribe to, plus per-occurrence download links for
    | "Add to calendar" buttons.
    |
    */

    'ics' => [
        'enabled' => true,
        'feed_url' => '/calendar.ics',
        'calendar_name' => env('APP_NAME', 'Calendar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    |
    | Expose a JSON API for occurrences, designed for JS-based calendar
    | components that build their view client-side. Disabled by default.
    |
    | The route is registered under the configured prefix using Laravel's
    | "api" middleware group, so CORS is handled by config/cors.php.
    |
    | GET /{route}?from=2026-01-01&to=2026-01-31&limit=50&sort=asc&tags=music,art&organizer={id}
    |
    | To enrich occurrences with extra fields (image, category, anything
    | derived from the entry), listen to the OccurrenceBuilding event. It
    | fires once per occurrence at cache rebuild — zero runtime cost on API
    | reads. Recipe in the event's class docblock:
    | ElSchneider\StatamicCalendar\Events\OccurrenceBuilding
    |
    */

    'api' => [
        'enabled' => env('STATAMIC_CALENDAR_API_ENABLED', false),
        'route' => env('STATAMIC_CALENDAR_API_ROUTE', 'api/calendar/occurrences'),
        'middleware' => env('STATAMIC_CALENDAR_API_MIDDLEWARE', 'api'),
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
