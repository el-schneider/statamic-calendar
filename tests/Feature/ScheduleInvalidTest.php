<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;

test('rejects schedule frequencies that require arguments', function () {
    config()->set('statamic-calendar.cache.rebuild_schedule', 'hourlyAt');

    $method = new ReflectionMethod(ServiceProvider::class, 'schedule');

    expect(fn () => $method->invoke(new ServiceProvider(app()), Mockery::mock(Schedule::class)))
        ->toThrow(InvalidArgumentException::class, 'hourlyAt')
        ->toThrow(InvalidArgumentException::class, 'everyMinute, hourly, twiceDaily, daily, weekly');
});
