<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Tests\ExistingCollectionTestCase;
use ElSchneider\StatamicCalendar\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Boot tests need the app booted against content that already exists on disk,
// so they get their own case instead of building fixtures per test.
pest()->extend(ExistingCollectionTestCase::class)->in('Boot');
