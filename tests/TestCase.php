<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

use ElSchneider\StatamicCalendar\ServiceProvider;
use Statamic\Testing\AddonTestCase;

/**
 * Extends Statamic's own AddonTestCase so addon registration (manifest,
 * providers, stache stores) tracks each Statamic version — the `Manifest`
 * class moved from `Statamic\Extend` (v5) to `Statamic\Addons` (v6), which a
 * hand-rolled copy would have to special-case.
 */
abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic-calendar.api.enabled', true);
    }
}
