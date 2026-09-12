<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\GraphQL\Types;

use Rebing\GraphQL\Support\EnumType;

class CalendarOccurrenceSortType extends EnumType
{
    public const NAME = 'CalendarOccurrenceSort';

    protected $attributes = [
        'name' => self::NAME,
        'values' => [
            'asc' => ['value' => 'asc'],
            'desc' => ['value' => 'desc'],
        ],
    ];
}
