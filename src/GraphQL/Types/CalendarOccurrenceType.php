<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\GraphQL\Types;

use Rebing\GraphQL\Support\Type;
use Statamic\Facades\GraphQL;

class CalendarOccurrenceType extends Type
{
    public const NAME = 'CalendarOccurrence';

    protected $attributes = ['name' => self::NAME];

    public function fields(): array
    {
        $fields = [
            'id' => ['type' => GraphQL::nonNull(GraphQL::id())],
            'entry_id' => ['type' => GraphQL::nonNull(GraphQL::id())],
            'title' => ['type' => GraphQL::nonNull(GraphQL::string())],
            'slug' => ['type' => GraphQL::nonNull(GraphQL::string())],
            'teaser' => ['type' => GraphQL::string()],
            'organizer_id' => ['type' => GraphQL::id()],
            'organizer_slug' => ['type' => GraphQL::string()],
            'organizer_title' => ['type' => GraphQL::string()],
            'organizer_url' => ['type' => GraphQL::string()],
            'tags' => ['type' => GraphQL::nonNull(GraphQL::listOf(GraphQL::nonNull(GraphQL::string())))],
            'start' => ['type' => GraphQL::nonNull(GraphQL::string())],
            'end' => ['type' => GraphQL::string()],
            'is_all_day' => ['type' => GraphQL::nonNull(GraphQL::boolean())],
            'is_recurring' => ['type' => GraphQL::nonNull(GraphQL::boolean())],
            'recurrence_description' => ['type' => GraphQL::string()],
            'url' => ['type' => GraphQL::nonNull(GraphQL::string())],
            'is_excluded' => ['type' => GraphQL::nonNull(GraphQL::boolean())],
            'replacement_date' => ['type' => GraphQL::string()],
            'replaces_date' => ['type' => GraphQL::string()],
        ];

        foreach (GraphQL::getExtraTypeFields(self::NAME) as $field => $closure) {
            $fields[$field] = $closure();
        }

        return $fields;
    }
}
