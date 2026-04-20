<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Http\Controllers;

use Carbon\Carbon;
use ElSchneider\StatamicCalendar\Facades\Occurrences;
use ElSchneider\StatamicCalendar\Occurrences\OccurrenceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $includeExcluded = $request->boolean('include_excluded');

        $occurrences = Occurrences::all($includeExcluded)
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

        if ($limit) {
            $occurrences = $occurrences->take($limit);
        }

        return new JsonResponse([
            'data' => $occurrences->map(fn (OccurrenceData $o) => $o->toArray())->values()->all(),
        ]);
    }
}
