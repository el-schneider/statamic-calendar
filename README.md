# Statamic Calendar

Recurring events and cached occurrences for Statamic.

## Features

- Define complex recurrence patterns using RFC 5545 (RRULE) via `rlanvin/php-rrule`
- Materialize occurrences into Laravel cache for fast listings
- Antlers tags for listing, current occurrence, next occurrences, and month grid
- Month calendar view — server-rendered, navigable via query params, no JS required
- iCalendar (.ics) feed for calendar app subscriptions + per-event "Add to calendar" downloads
- Two URL strategies: query string (default, Statamic-native) or date segments
- Example templates for index (list, archive, calendar grid) and show pages

## Installation

```bash
composer require el-schneider/statamic-calendar
```

Publish the config:

```bash
php artisan vendor:publish --tag=statamic-calendar
```

## URL Strategies

The addon supports two strategies for occurrence URLs. Configure in `config/statamic-calendar.php`:

### Query String (default)

Uses Statamic's native collection routing. The addon doesn't register any routes — your collection config handles everything:

```yaml
# content/collections/events.yaml
route: '/events/{slug}'
```

Occurrence URLs look like `/events/my-event?date=2025-03-15`.

### Date Segments (opt-in)

For SEO-friendly date-based URLs like `/calendar/2025/03/15/my-event`. Enable in config:

```php
'url' => [
    'strategy' => 'date_segments',
    'date_segments' => [
        'prefix' => 'calendar',
    ],
],
```

The addon registers a route at `/{prefix}/{year}/{month}/{day}/{slug}`.

## iCalendar (.ics) Export

The addon exposes an .ics feed that calendar apps (Apple Calendar, Google Calendar, Outlook) can subscribe to. Enabled by default.

### Subscribable Feed

`GET /calendar.ics` — returns all cached occurrences as a standard iCalendar feed. Calendar apps can subscribe to this URL and will receive updates as events change.

### Single-Event Download

`GET /calendar.ics/{occurrenceId}` — returns a single occurrence as a downloadable .ics file. Use this for "Add to calendar" buttons. The response includes a `Content-Disposition: attachment` header.

### Configuration

```php
// config/statamic-calendar.php
'ics' => [
    'enabled' => true,           // set to false to disable .ics routes
    'feed_url' => '/calendar.ics',
    'calendar_name' => env('APP_NAME', 'Calendar'),
],
```

### Template Usage

```antlers
{{-- Subscribe link --}}
<a href="{{ calendar:ics_url }}">Subscribe to calendar</a>

{{-- Per-event download inside a calendar loop --}}
{{ calendar from="now" limit="10" }}
  <a href="{{ calendar:ics_download_url :occurrence_id="id" }}">
    Add to calendar
  </a>
{{ /calendar }}
```

## Setting Up Templates

### Events Index

Create a page or route that uses the `{{ calendar }}` tag. For example, add to `routes/web.php`:

```php
Route::statamic('events', 'events/index', [
    'title' => 'Upcoming Events',
]);
```

Then create `resources/views/events/index.antlers.html`:

```antlers
<h1>Upcoming Events</h1>

{{ calendar from="now" limit="20" }}
  <a href="{{ url }}">
    <h2>{{ title }}</h2>
    <p>{{ start format="l, M j, Y" }}</p>
    {{ if is_recurring }}
      <p>{{ recurrence_description }}</p>
    {{ /if }}
  </a>
{{ /calendar }}
```

### Event Show Page

Set the collection template to `events/show`, then create `resources/views/events/show.antlers.html`:

```antlers
<h1>{{ title }}</h1>

{{ calendar:current_occurrence }}
  <p>{{ start format="l, F j, Y" }}</p>
  {{ if !is_all_day }}
    <p>
      {{ start format="g:i A" }}
      {{ if end }}– {{ end format="g:i A" }}{{ /if }}
    </p>
  {{ else }}
    <p>All day</p>
  {{ /if }}
  {{ if is_recurring }}
    <p>Repeats: {{ recurrence_description }}</p>
  {{ /if }}
{{ /calendar:current_occurrence }}
{{ calendar:next_occurrences :entry="id" limit="5" }}
  <a href="{{ url }}">{{ start format="M j, Y" }}</a>
{{ /calendar:next_occurrences }}
```

The `{{ calendar:current_occurrence }}` tag reads the `?date=` query parameter and resolves the matching occurrence for the current entry. When using the `date_segments` strategy, the date is extracted from the URL instead.

## Antlers Tags

### `{{ calendar }}`

Lists occurrences from the cache (or resolves them live for non-default collections).

| Parameter    | Description              | Default      |
| ------------ | ------------------------ | ------------ |
| `from`       | Start date               | `now`        |
| `to`         | End date                 | —            |
| `limit`      | Max occurrences          | —            |
| `collection` | Collection handle        | config value |
| `tags`       | Filter by taxonomy terms | —            |

### `{{ calendar:month }}`

Renders a month grid with weeks, days, and occurrences. Navigation via query params, fully server-rendered. See the example index template for usage.

| Parameter        | Description                                                      | Default      |
| ---------------- | ---------------------------------------------------------------- | ------------ |
| `param`          | Query string parameter name (allows multiple calendars per page) | `month`      |
| `week_starts_on` | Day of week (`0` = Sunday, `1` = Monday)                         | `1`          |
| `fixed_rows`     | Always render 6 rows for consistent grid height                  | `false`      |
| `collection`     | Collection handle                                                | config value |
| `tags`           | Filter by taxonomy terms                                         | —            |

Variables available inside the tag pair: `month_label`, `year`, `month`, `prev_url`, `next_url`, `today`, `day_labels` (loop with `label`, `full_label`), and `weeks` → `days` → `date`, `day`, `is_current_month`, `is_today`, `occurrences`.

### `{{ calendar:current_occurrence }}`

Resolves the current occurrence for the entry in context, based on the `?date=` query param. Use as a tag pair — variables available inside:

- `start` — Carbon date
- `end` — Carbon date (nullable)
- `is_all_day` — boolean
- `is_recurring` — boolean
- `recurrence_description` — human-readable recurrence rule
- `occurrence_url` — the occurrence URL

### `{{ calendar:next_occurrences }}`

Lists upcoming occurrences for a specific entry.

| Parameter | Description     | Default              |
| --------- | --------------- | -------------------- |
| `entry`   | Entry ID        | current context `id` |
| `from`    | Start date      | `now`                |
| `to`      | End date        | —                    |
| `limit`   | Max occurrences | `5`                  |

### `{{ calendar:ics_url }}`

Returns the URL to the .ics calendar feed. Use it to offer a "Subscribe" link:

```antlers
<a href="{{ calendar:ics_url }}">Subscribe to calendar</a>
```

### `{{ calendar:ics_download_url }}`

Returns the .ics download URL for a single occurrence. Use inside any `{{ calendar }}` loop to offer an "Add to calendar" button:

```antlers
{{ calendar from="now" limit="10" }}
  <h2>{{ title }}</h2>
  <a href="{{ calendar:ics_download_url :occurrence_id="id" }}">Add to calendar</a>
{{ /calendar }}
```

| Parameter      | Description  | Default              |
| -------------- | ------------ | -------------------- |
| `occurrence_id` | Occurrence ID (`{entry_id}-{Y-m-d-His}`) | current context `id` |

### `{{ calendar:for_organizer }}`

Lists upcoming occurrences for an organizer (from cache).

| Parameter   | Description        | Default              |
| ----------- | ------------------ | -------------------- |
| `organizer` | Organizer entry ID | current context `id` |
| `limit`     | Max occurrences    | `5`                  |

## Configuration

Key options in `config/statamic-calendar.php`:

| Key                        | Description                       | Default                         |
| -------------------------- | --------------------------------- | ------------------------------- |
| `collection`               | Source collection handle          | `events`                        |
| `fields.dates`             | Grid field handle                 | `dates`                         |
| `url.strategy`             | `query_string` or `date_segments` | `query_string`                  |
| `url.query_string.param`   | Query parameter name              | `date`                          |
| `url.date_segments.prefix` | URL prefix for date segments      | `calendar`                      |
| `ics.enabled`              | Enable .ics feed routes           | `true`                          |
| `ics.feed_url`             | Feed URL path                     | `/calendar.ics`                 |
| `ics.calendar_name`        | Calendar name in .ics output      | `APP_NAME`                      |
| `cache.key`                | Cache store key                   | `statamic_calendar.occurrences` |
| `cache.days_ahead`         | Recurrence expansion window       | `365`                           |

## Cache

Occurrences are materialized into Laravel's cache for fast listing. The cache rebuilds automatically when entries are saved or deleted.

Manual rebuild:

```bash
php artisan occurrences:rebuild
```

## Example Blueprint

Publish the example blueprint:

```bash
php artisan vendor:publish --tag=statamic-calendar-examples
```
