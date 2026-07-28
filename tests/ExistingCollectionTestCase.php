<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Tests;

/**
 * Boots the app with the events collection already on disk — the state of every
 * real install, and the one that used to skip calendar URL registration.
 */
abstract class ExistingCollectionTestCase extends TestCase
{
    private const COLLECTION = __DIR__.'/__fixtures__/content/collections/events.yaml';

    protected function tearDown(): void
    {
        @unlink(self::COLLECTION);

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        @mkdir(dirname(self::COLLECTION), 0755, true);
        file_put_contents(self::COLLECTION, "title: Events\n");
    }
}
