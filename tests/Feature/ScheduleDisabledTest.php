<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

test('does not schedule the occurrence cache rebuild when disabled', function () {
    config()->set('statamic-calendar.cache.schedule_rebuild', false);

    $schedule = Mockery::mock(Schedule::class);
    $schedule->shouldNotReceive('command');

    $method = new ReflectionMethod(ServiceProvider::class, 'schedule');
    $method->invoke(new ServiceProvider(app()), $schedule);
});
