<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tags;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\Occurrence;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceResolver;
use Illuminate\Support\Facades\URL;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Extensions\Pagination\LengthAwarePaginator;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Tags\Concerns\OutputsItems;
use Statamic\Tags\Tags;

class Calendar extends Tags
{
    use OutputsItems;

    protected static $handle = 'calendar';

    public function __construct(
        protected OccurrenceResolver $resolver
    ) {}

    public function index(): mixed
    {
        $collection = (string) $this->params->get('collection', config('statamic-calendar.collection', 'events'));
        $from = $this->params->has('from') ? Carbon::parse((string) $this->params->get('from')) : Carbon::now();
        $to = $this->params->has('to') ? Carbon::parse((string) $this->params->get('to')) : null;
        $limit = $this->params->int('limit');
        $sort = (string) $this->params->get('sort', 'asc');
        $paginate = $this->params->int('paginate');
        $pageName = (string) $this->params->get('page_name', 'page');

        $tags = $this->params->get('tags');

        if ($collection === config('statamic-calendar.collection', 'events')) {
            return $this->indexFromCache($from, $to, $limit, $tags, $sort, $paginate, $pageName);
        }

        return $this->indexFromResolver($collection, $from, $to, $limit, $tags, $sort, $paginate, $pageName);
    }

    /**
     * Resolves the current occurrence for an entry based on a date query param.
     *
     * Usage: {{ calendar:current_occurrence }} ... {{ /calendar:current_occurrence }}
     */
    public function currentOccurrence(): mixed
    {
        $param = (string) config('statamic-calendar.url.query_string.param', 'date');
        $dateString = request()->query($param);

        $entryId = $this->context->get('id');

        if ($entryId instanceof \Statamic\Fields\Value) {
            $entryId = $entryId->value();
        }

        $entry = (is_string($entryId) || is_int($entryId)) ? Entry::find((string) $entryId) : null;

        if (! $entry || ! $dateString) {
            return '';
        }

        $date = Carbon::parse((string) $dateString);
        $occurrence = $this->resolver->findOccurrenceOnDate($entry, $date);

        if (! $occurrence) {
            return '';
        }

        return $this->parse([
            'occurrence_id' => $entry->id().'-'.$occurrence->start->format('Y-m-d-His'),
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'occurrence_url' => $occurrence->url(),
        ]);
    }

    /**
     * Usage: {{ calendar:for_organizer :organizer="id" limit="5" }}
     */
    public function forOrganizer(): mixed
    {
        $organizerId = $this->params->get('organizer') ?? $this->context->get('id');
        $limit = $this->params->int('limit', 5);
        $from = $this->params->has('from') ? Carbon::parse((string) $this->params->get('from')) : Carbon::now();
        $paginate = $this->params->int('paginate');
        $pageName = (string) $this->params->get('page_name', 'page');

        $occurrences = Occurrences::forOrganizer((is_string($organizerId) || is_int($organizerId)) ? (string) $organizerId : null)
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->sortBy(fn (OccurrenceData $o) => $o->start);

        if ($paginate > 0) {
            $mapped = $occurrences->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))->values();
            $page = (int) request()->input($pageName, 1);
            $paginator = new LengthAwarePaginator(
                $mapped->forPage($page, $paginate),
                $mapped->count(),
                $paginate,
                $page,
                ['path' => request()->url(), 'pageName' => $pageName]
            );

            return $this->output($paginator);
        }

        return $occurrences
            ->take($limit)
            ->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))
            ->values()
            ->all();
    }

    /**
     * Returns the URL to the .ics calendar feed.
     *
     * Usage: {{ calendar:ics_url }}
     */
    public function icsUrl(): string
    {
        return URL::route('statamic-calendar.ics.feed');
    }

    /**
     * Returns the .ics download URL for a single occurrence.
     *
     * Usage: {{ calendar:ics_download_url :occurrence_id="id" }}
     *
     * The occurrence ID follows the format "{entry_id}-{Y-m-d-His}",
     * which is available as `id` in any `{{ calendar }}` loop.
     */
    public function icsDownloadUrl(): string
    {
        $id = (string) ($this->params->get('occurrence_id') ?? $this->context->get('id'));

        return URL::route('statamic-calendar.ics.download', $id);
    }

    /**
     * Returns a month grid with weeks, days, and occurrences.
     *
     * Usage: {{ calendar:month param="month" week_starts_on="1" }}
     */
    public function month(): mixed
    {
        $param = (string) $this->params->get('param', 'month');
        $weekStartsOn = $this->params->int('week_starts_on', 1);
        $fixedRows = $this->params->bool('fixed_rows', false);
        $collection = (string) $this->params->get('collection', config('statamic-calendar.collection', 'events'));
        $tags = $this->params->get('tags');

        $monthValue = request()->query($param);
        $current = ($monthValue && is_string($monthValue) && preg_match('/^\d{4}-\d{2}$/', $monthValue))
            ? Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth()
            : Carbon::now()->startOfMonth();

        [$gridStart, $gridEnd] = $this->monthGridBoundaries($current, $weekStartsOn, $fixedRows);

        $occurrences = ($collection === config('statamic-calendar.collection', 'events'))
            ? $this->indexFromCache($gridStart, $gridEnd, null, $tags)
            : $this->indexFromResolver($collection, $gridStart, $gridEnd, null, $tags);

        $grouped = collect($occurrences)->groupBy(
            fn (array $o) => Carbon::parse($o['start'])->format('Y-m-d')
        );

        $weeks = $this->buildWeeks($gridStart, $gridEnd, $current, $grouped);
        $dayLabels = $this->buildDayLabels($gridStart);

        $query = request()->query();
        $query[$param] = $current->copy()->subMonth()->format('Y-m');
        $prevUrl = '?'.http_build_query($query);
        $query[$param] = $current->copy()->addMonth()->format('Y-m');
        $nextUrl = '?'.http_build_query($query);

        return $this->parse([
            'month_label' => $current->translatedFormat('F Y'),
            'year' => $current->year,
            'month' => $current->month,
            'prev_url' => $prevUrl,
            'next_url' => $nextUrl,
            'today' => Carbon::today()->format('Y-m-d'),
            'day_labels' => $dayLabels,
            'weeks' => $weeks,
        ]);
    }

    public function nextOccurrences(): array
    {
        $entryId = $this->params->get('entry') ?? $this->context->get('id');
        $entry = (is_string($entryId) || is_int($entryId)) ? Entry::find((string) $entryId) : null;

        if (! $entry) {
            return [];
        }

        $contextStart = $this->getContextStart();

        $from = $this->params->get('from');
        $from = $from ? Carbon::parse((string) $from) : ($contextStart ?? Carbon::now());

        $to = $this->params->has('to') ? Carbon::parse((string) $this->params->get('to')) : null;
        $limit = $this->params->int('limit', 5);

        $occurrences = $this->resolver->resolve($entry, $from, $to, $limit);

        if ($contextStart && ! $this->params->bool('include_current', false)) {
            $occurrences = $occurrences->reject(fn (Occurrence $o) => $o->start->equalTo($contextStart));
        }

        return $occurrences->map(fn (Occurrence $o) => $this->occurrenceToArray($o))->values()->all();
    }

    protected function paginatedOutput($paginator): mixed
    {
        $paginator->withQueryString();

        if ($window = $this->params->int('on_each_side')) {
            $paginator->onEachSide($window);
        }

        $as = $this->getPaginationResultsKey();
        $items = $paginator->getCollection();

        return array_merge([
            $as => $items,
            'paginate' => $this->getPaginationData($paginator),
        ], $this->extraOutput($items));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthGridBoundaries(Carbon $month, int $weekStartsOn, bool $fixedRows = false): array
    {
        $gridStart = $month->copy()->startOfMonth();
        while ($gridStart->dayOfWeek !== $weekStartsOn) {
            $gridStart->subDay();
        }

        $gridEnd = $month->copy()->endOfMonth();
        $weekEndsOn = ($weekStartsOn + 6) % 7;
        while ($gridEnd->dayOfWeek !== $weekEndsOn) {
            $gridEnd->addDay();
        }

        if ($fixedRows) {
            $days = $gridStart->diffInDays($gridEnd) + 1;
            while ($days < 42) {
                $gridEnd->addWeek();
                $days += 7;
            }
        }

        $gridEnd->endOfDay();

        return [$gridStart, $gridEnd];
    }

    private function buildWeeks(Carbon $gridStart, Carbon $gridEnd, Carbon $current, $grouped): array
    {
        $weeks = [];
        $day = $gridStart->copy();

        while ($day->lte($gridEnd)) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $day->format('Y-m-d');
                $days[] = [
                    'date' => $day->copy(),
                    'day' => $day->day,
                    'is_current_month' => $day->month === $current->month && $day->year === $current->year,
                    'is_today' => $day->isToday(),
                    'occurrences' => ($grouped[$dateKey] ?? collect())->values()->all(),
                ];
                $day->addDay();
            }
            $weeks[] = ['days' => $days];
        }

        return $weeks;
    }

    private function buildDayLabels(Carbon $gridStart): array
    {
        $labels = [];
        $day = $gridStart->copy();
        for ($i = 0; $i < 7; $i++) {
            $labels[] = [
                'label' => $day->translatedFormat('D'),
                'full_label' => $day->translatedFormat('l'),
            ];
            $day->addDay();
        }

        return $labels;
    }

    private function indexFromCache(Carbon $from, ?Carbon $to, ?int $limit, $tags, string $sort = 'asc', int $paginate = 0, string $pageName = 'page'): mixed
    {
        $occurrences = Occurrences::all()
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->when($to, fn ($c) => $c->filter(fn (OccurrenceData $o) => $o->start->lte($to)));

        if ($tags) {
            $tagSlugs = $this->normalizeTagSlugs($tags);
            if ($tagSlugs) {
                $occurrences = $occurrences->filter(fn (OccurrenceData $o) => $o->hasAnyTag($tagSlugs));
            }
        }

        $occurrences = $sort === 'desc'
            ? $occurrences->sortByDesc(fn (OccurrenceData $o) => $o->start)
            : $occurrences->sortBy(fn (OccurrenceData $o) => $o->start);

        if ($paginate > 0) {
            $mapped = $occurrences->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))->values();
            $page = (int) request()->input($pageName, 1);
            $paginator = new LengthAwarePaginator(
                $mapped->forPage($page, $paginate),
                $mapped->count(),
                $paginate,
                $page,
                ['path' => request()->url(), 'pageName' => $pageName]
            );

            return $this->output($paginator);
        }

        if ($limit) {
            $occurrences = $occurrences->take($limit);
        }

        return $occurrences->map(fn (OccurrenceData $o) => $this->occurrenceDataToArray($o))->values()->all();
    }

    /**
     * @return array<string>
     */
    private function normalizeTagSlugs($tags): array
    {
        if ($tags instanceof TermQueryBuilder) {
            $tags = $tags->get();
        }

        if (is_string($tags)) {
            $tags = preg_split('/[|,]/', $tags) ?: [];
        }

        return collect($tags)
            ->map(function ($tag) {
                if ($tag instanceof Term) {
                    return $tag->slug();
                }

                if (is_array($tag)) {
                    return $tag['slug'] ?? null;
                }

                return is_string($tag) ? $tag : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function indexFromResolver(string $collection, Carbon $from, ?Carbon $to, ?int $limit, $tags, string $sort = 'asc', int $paginate = 0, string $pageName = 'page'): mixed
    {
        $query = Entry::query()->where('collection', $collection);

        if ($tags) {
            $tagSlugs = $this->normalizeTagSlugs($tags);
            $taxonomy = (string) config('statamic-calendar.fields.tags.handle', 'event_tags');
            $prefixedSlugs = collect($tagSlugs)
                ->map(fn ($slug) => "{$taxonomy}::{$slug}")
                ->all();

            if ($prefixedSlugs) {
                $query->whereTaxonomyIn($prefixedSlugs);
            }
        }

        $entries = $query->get();

        $allOccurrences = collect();

        $resolverLimit = $paginate > 0 ? null : $limit;

        foreach ($entries as $entry) {
            $occurrences = $this->resolver->resolve($entry, $from, $to, $resolverLimit);
            $allOccurrences = $allOccurrences->merge($occurrences);
        }

        $allOccurrences = $sort === 'desc'
            ? $allOccurrences->sortByDesc(fn (Occurrence $o) => $o->start)
            : $allOccurrences->sortBy(fn (Occurrence $o) => $o->start);

        if ($paginate > 0) {
            $mapped = $allOccurrences->map(fn (Occurrence $o) => $this->occurrenceToArray($o))->values();
            $page = (int) request()->input($pageName, 1);
            $paginator = new LengthAwarePaginator(
                $mapped->forPage($page, $paginate),
                $mapped->count(),
                $paginate,
                $page,
                ['path' => request()->url(), 'pageName' => $pageName]
            );

            return $this->output($paginator);
        }

        if ($limit) {
            $allOccurrences = $allOccurrences->take($limit);
        }

        return $allOccurrences->map(fn (Occurrence $o) => $this->occurrenceToArray($o))->values()->all();
    }

    private function getContextStart(): ?Carbon
    {
        $contextStart = $this->context->get('start');

        if ($contextStart instanceof Carbon) {
            return $contextStart;
        }

        if ($contextStart instanceof \Statamic\Fields\Value) {
            $value = $contextStart->value();

            return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        }

        return null;
    }

    private function occurrenceToArray(Occurrence $occurrence): array
    {
        $augmented = $occurrence->entry->toAugmentedArray();

        return array_merge($augmented, [
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'url' => $occurrence->url(),
        ]);
    }

    private function occurrenceDataToArray(OccurrenceData $occurrence): array
    {
        return [
            'id' => $occurrence->entryId,
            'title' => $occurrence->title,
            'slug' => $occurrence->slug,
            'teaser' => $occurrence->teaser,
            'start' => $occurrence->start,
            'end' => $occurrence->end,
            'is_all_day' => $occurrence->isAllDay,
            'is_recurring' => $occurrence->isRecurring,
            'recurrence_description' => $occurrence->recurrenceDescription,
            'url' => $occurrence->url,
        ];
    }
}
