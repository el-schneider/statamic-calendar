<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Tests\DisabledScheduleTestCase;
use Illuminate\Console\Scheduling\Schedule;

uses(DisabledScheduleTestCase::class);

test('does not schedule the occurrence cache rebuild when disabled', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->contains(fn ($event) => str_contains((string) $event->command, 'occurrences:rebuild'));

    expect($scheduled)->toBeFalse();
});
