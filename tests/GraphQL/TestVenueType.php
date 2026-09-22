<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests\GraphQL;

use Rebing\GraphQL\Support\Type;
use Statamic\Facades\GraphQL;

class TestVenueType extends Type
{
    public const NAME = 'CalendarTestVenue';

    protected $attributes = ['name' => self::NAME];

    public function fields(): array
    {
        return [
            'name' => ['type' => GraphQL::string()],
        ];
    }
}
