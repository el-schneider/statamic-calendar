<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

use ElSchneider\StatamicCalendar\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic-calendar.api.enabled', true);
    }
}
