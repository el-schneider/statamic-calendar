# Statamic Calendar

Recurring events and cached occurrences for Statamic.

## Features

- Define complex recurrence patterns using RFC 5545 (RRULE) via `rlanvin/php-rrule`
- Materialize occurrences into Laravel cache for fast listings
- Frontend occurrence pages via date-segment URLs
- Antlers tags for listing and next-occurrences

## How to Install

You can install this addon via Composer:

```bash
composer require el-schneider/statamic-calendar
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=statamic-calendar
```

Key options in `config/statamic-calendar.php`:

- `collection`: event collection handle (default `events`)
- `fields.*`: map date grid / tag field / organizer field
- `url.strategy`: `date_segments` or `query_string`
- `url.date_segments.prefix`: route prefix for occurrence pages
- `cache.key`: cache key used for materialized occurrences
- `cache.days_ahead`: how far ahead recurrences are expanded

## Occurrence Route

When `url.strategy=date_segments`, occurrences are served at:

`/{prefix}/{year}/{month}/{day}/{slug}`

The route is registered by the addon and named `events.occurrence`.

## Antlers Tags

List occurrences:

```antlers
{{ events from="now" to="+1 month" limit="10" }}
    <a href="{{ url }}">{{ start format="Y-m-d" }} - {{ title }}</a>
{{ /events }}
```

Filter by tags:

```antlers
{{ events event_tags="workshop|outdoor" }}
    ...
{{ /events }}
```

Next occurrences for an entry:

```antlers
{{ events:next_occurrences :entry="id" from="now" limit="5" }}
    {{ start format="Y-m-d" }}
{{ /events:next_occurrences }}
```

Occurrences for an organizer (alias: `for_member`):

```antlers
{{ events:for_organizer :organizer="id" limit="5" }}
    ...
{{ /events:for_organizer }}
{{ events:for_member :member="id" limit="5" }}
    ...
{{ /events:for_member }}
```

## Cache Rebuild Command

```bash
php artisan occurrences:rebuild
```

## Example Blueprint

This addon ships an example events blueprint (including the recurrence grid).
Publish it into your project:

```bash
php artisan vendor:publish --tag=statamic-calendar-examples
```

## How to Use

Here's where you can explain how to use this wonderful addon.
