<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\GraphQL\Queries;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\GraphQL\Types\CalendarOccurrenceSortType;
use ElSchneider\StatamicCalendar\GraphQL\Types\CalendarOccurrenceStatusType;
use ElSchneider\StatamicCalendar\GraphQL\Types\CalendarOccurrenceType;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceWindow;
use Illuminate\Pagination\LengthAwarePaginator;
use Statamic\Facades\GraphQL;
use Statamic\GraphQL\Queries\Query;

class CalendarOccurrencesQuery extends Query
{
    protected $attributes = ['name' => 'calendarOccurrences'];

    public function type(): \GraphQL\Type\Definition\Type
    {
        return GraphQL::paginate(CalendarOccurrenceType::NAME);
    }

    public function args(): array
    {
        return [
            'from' => ['type' => GraphQL::string()],
            'to' => ['type' => GraphQL::string()],
            'status' => ['type' => GraphQL::listOf(GraphQL::nonNull(GraphQL::type(CalendarOccurrenceStatusType::NAME)))],
            'tags' => ['type' => GraphQL::listOf(GraphQL::nonNull(GraphQL::string()))],
            'organizer' => ['type' => GraphQL::id()],
            'sort' => ['type' => GraphQL::type(CalendarOccurrenceSortType::NAME)],
            'limit' => ['type' => GraphQL::int()],
            'page' => ['type' => GraphQL::int()],
            'include_excluded' => ['type' => GraphQL::boolean()],
        ];
    }

    public function resolve($root, $args): LengthAwarePaginator
    {
        $now = OccurrenceWindow::now();
        $statuses = $args['status'] ?? [];
        $from = isset($args['from'])
            ? Carbon::parse($args['from'])
            : (empty($statuses) ? $now : null);
        $to = isset($args['to']) ? Carbon::parse($args['to']) : null;
        $includeExcluded = $args['include_excluded'] ?? false;
        $maxPerPage = max(1, (int) config('statamic-calendar.graphql.max_per_page', 100));
        $limit = min($args['limit'] ?? 15, $maxPerPage);
        $page = $args['page'] ?? 1;

        $occurrences = (empty($statuses)
            ? Occurrences::all($includeExcluded)
            : Occurrences::status($statuses, $now, $includeExcluded))
            ->filter(fn (OccurrenceData $occurrence) => OccurrenceWindow::matches($occurrence, $from, $to));

        if (! empty($args['tags'])) {
            $occurrences = $occurrences->filter(fn (OccurrenceData $occurrence) => $occurrence->hasAnyTag($args['tags']));
        }

        if (isset($args['organizer'])) {
            $occurrences = $occurrences->filter(fn (OccurrenceData $occurrence) => $occurrence->organizerId === $args['organizer']);
        }

        $occurrences = ($args['sort'] ?? 'asc') === 'desc'
            ? $occurrences->sortByDesc(fn (OccurrenceData $occurrence) => $occurrence->start)
            : $occurrences->sortBy(fn (OccurrenceData $occurrence) => $occurrence->start);

        $items = $occurrences->values();

        return new LengthAwarePaginator(
            $items->forPage($page, $limit)->map(fn (OccurrenceData $occurrence) => $occurrence->toArray())->values(),
            $items->count(),
            $limit,
            $page,
            ['path' => request()->url()]
        );
    }

    protected function rules(array $args = []): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
