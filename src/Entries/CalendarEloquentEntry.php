<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Entries;

use ElSchneider\StatamicCalendar\Entries\Concerns\HasCalendarUrls;
use Statamic\Eloquent\Entries\Entry;

class CalendarEloquentEntry extends Entry
{
    use HasCalendarUrls;
}
