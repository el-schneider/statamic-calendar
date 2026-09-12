<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\GraphQL\Types;

use Rebing\GraphQL\Support\EnumType;

class CalendarOccurrenceStatusType extends EnumType
{
    public const NAME = 'CalendarOccurrenceStatus';

    protected $attributes = [
        'name' => self::NAME,
        'values' => [
            'upcoming' => ['value' => 'upcoming'],
            'ongoing' => ['value' => 'ongoing'],
            'past' => ['value' => 'past'],
        ],
    ];
}
