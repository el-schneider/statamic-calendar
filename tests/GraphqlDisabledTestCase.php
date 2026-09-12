<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

class GraphqlDisabledTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic-calendar.graphql.enabled', false);
    }
}
