<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

test('schedules the occurrence cache rebuild daily by default', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'occurrences:rebuild'));

    expect($event)->not->toBeNull()
        ->expression->toBe('0 0 * * *')
        ->withoutOverlapping->toBeTrue();
});
