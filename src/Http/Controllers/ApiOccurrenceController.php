<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Http\Controllers;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiOccurrenceController
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->has('from') ? Carbon::parse($request->query('from')) : Carbon::now();
        $to = $request->has('to') ? Carbon::parse($request->query('to')) : null;
        $limit = $request->integer('limit') ?: null;
        $sort = $request->query('sort', 'asc') === 'desc' ? 'desc' : 'asc';
        $tags = $request->query('tags');
        $organizer = $request->query('organizer');

        $occurrences = Occurrences::all()
            ->filter(fn (OccurrenceData $o) => $o->start->gte($from))
            ->when($to, fn ($c) => $c->filter(fn (OccurrenceData $o) => $o->start->lte($to)));

        if ($tags) {
            $tagSlugs = array_filter(explode(',', (string) $tags));
            $occurrences = $occurrences->filter(fn (OccurrenceData $o) => $o->hasAnyTag($tagSlugs));
        }

        if ($organizer) {
            $occurrences = $occurrences->filter(fn (OccurrenceData $o) => $o->organizerId === $organizer);
        }

        $occurrences = $sort === 'desc'
            ? $occurrences->sortByDesc(fn (OccurrenceData $o) => $o->start)
            : $occurrences->sortBy(fn (OccurrenceData $o) => $o->start);

        // Paginate when either page or per_page is present; silently ignoring
        // per_page when page is absent surprises API consumers.
        if ($request->has('page') || $request->has('per_page')) {
            $page = max(1, $request->integer('page', 1));
            $maxPerPage = max(1, (int) config('statamic-calendar.api.max_per_page', 100));
            $perPage = max(1, min($request->integer('per_page', 15), $maxPerPage));
            $total = $occurrences->count();
            $items = $occurrences->values()->forPage($page, $perPage);

            $paginator = new LengthAwarePaginator(
                $items->map(fn (OccurrenceData $o) => $o->toArray())->values(),
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'pageName' => 'page']
            );
            $paginator->appends($request->query());

            return new JsonResponse($paginator->toArray());
        }

        if ($limit) {
            $occurrences = $occurrences->take($limit);
        }

        return new JsonResponse([
            'data' => $occurrences->map(fn (OccurrenceData $o) => $o->toArray())->values()->all(),
        ]);
    }
}
