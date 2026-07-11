<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Entries;

use ElSchneider\StatamicCalendar\Entries\Concerns\HasCalendarUrls;
use Statamic\Entries\Entry;

class CalendarEntry extends Entry
{
    use HasCalendarUrls;
}
