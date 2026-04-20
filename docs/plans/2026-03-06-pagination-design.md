# Pagination Support Design

## Problem

The REST API and Antlers tags have no way to paginate through large result sets. Archive pages with hundreds of past occurrences need proper pagination.

## Approach

Use Statamic's `OutputsItems` trait on the `Calendar` tag and manually construct `LengthAwarePaginator` instances from in-memory collections. No shared abstraction between API and tag — the overlap is too small to justify it.

## Design

### Components

1. **`Calendar` tag** — add `OutputsItems` trait, modify `index()` and `forOrganizer()` to optionally paginate and return via `$this->output()`
2. **`ApiOccurrenceController`** — add `page`/`per_page` params, return paginated response shape when `page` is present
3. **`config/statamic-calendar.php`** — add `api.max_per_page` setting (default: 100)

No new classes.

### Data Flow

#### Antlers Tag

```
params (paginate, page_name, as)
  → filter/sort occurrences (existing logic, unchanged)
  → if paginate param set:
      → forPage($currentPage, $perPage) on the collection
      → wrap in LengthAwarePaginator
      → return $this->output($paginator)  ← OutputsItems handles the rest
  → else:
      → if limit: take($limit)
      → return $this->output($collection) ← OutputsItems handles as/total_results/no_results
```

When `paginate` and `limit` are both set, `paginate` wins silently.

Both `indexFromCache` and `indexFromResolver` support pagination.

The `output()` call also enables `as` param support for non-paginated results.

#### API

```
request params (page, per_page, + existing filters)
  → filter/sort occurrences (existing logic, unchanged)
  → if page OR per_page param present:
      → clamp per_page to config max
      → forPage($page, $perPage)
      → return Laravel's default LengthAwarePaginator JSON shape
  → else:
      → if limit: take($limit)
      → return { data } (unchanged, no breaking change)
```

Default `per_page`: 15, default `page`: 1. When `page` and `limit` are both present, `page` wins silently.

Uses Laravel's default paginator JSON shape (flat keys: `data`, `current_page`, `per_page`, `total`, `last_page`, `*_url` links). Existing query params are preserved in pagination URLs.

### Tags Supporting Pagination

| Tag | Pagination | Reason |
|-----|-----------|--------|
| `{{ calendar }}` | Yes | Archive/listing pages |
| `{{ calendar:for_organizer }}` | Yes | Organizer pages with many events |
| `{{ calendar:month }}` | No | Bounded by month grid |
| `{{ calendar:current_occurrence }}` | No | Single occurrence |
| `{{ calendar:next_occurrences }}` | No | Small "up next" lists |

### Edge Cases

- **`page` exceeds total pages** — empty `data`, correct `total`/`last_page` (standard Laravel behavior)
- **`per_page=0` or negative** — clamped to 1 on API; falsy `paginate` on tag means no pagination
- **`page` without `per_page`** — defaults to 15
- **Empty result set** — `data: [], total: 0, last_page: 1`

### Template Usage

```antlers
{{ calendar from="2020-01-01" to="now" paginate="20" as="events" sort="desc" }}
  {{ events }}
    <a href="{{ url }}">
      <h2>{{ title }}</h2>
      <p>{{ start format="M j, Y" }}</p>
    </a>
  {{ /events }}

  {{ paginate }}
    {{ if prev_page }}<a href="{{ prev_page }}">← Previous</a>{{ /if }}
    <span>Page {{ current_page }} of {{ total_pages }}</span>
    {{ if next_page }}<a href="{{ next_page }}">Next →</a>{{ /if }}
  {{ /paginate }}
{{ /calendar }}
```

## Out of Scope

- Cursor-based pagination
- Pagination for `month`, `current_occurrence`, `next_occurrences` tags
- Chunked output support
- Refactoring to a query builder pattern

## Open Questions

None.
