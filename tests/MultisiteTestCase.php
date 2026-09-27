<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

use Statamic\Facades\Site;

/**
 * Sites exist before boot, so date segment routes register for every site path
 * ahead of Statamic's catch-all route, as they do in a real multi-site install.
 * Covers both flavours: a subdirectory site and a site on its own domain.
 */
abstract class MultisiteTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.multisite', true);
        $app['config']->set('statamic-calendar.url.strategy', 'date_segments');

        $app->booting(fn () => Site::setSites([
            'default' => ['name' => 'English', 'url' => 'http://localhost/', 'locale' => 'en_US'],
            'de' => ['name' => 'Deutsch', 'url' => 'http://localhost/de/', 'locale' => 'de_DE'],
            'fr' => ['name' => 'Français', 'url' => 'http://example.fr/', 'locale' => 'fr_FR'],
        ]));
    }
}
