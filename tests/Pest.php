<?php

declare(strict_types=1);

use ElSchneider\StatamicCalendar\Tests\ExistingCollectionTestCase;
use ElSchneider\StatamicCalendar\Tests\GraphqlDisabledTestCase;
use ElSchneider\StatamicCalendar\Tests\GraphqlWithoutRestTestCase;
use ElSchneider\StatamicCalendar\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(GraphqlDisabledTestCase::class)->in('GraphQL/GraphqlDisabledTest.php');
pest()->extend(GraphqlWithoutRestTestCase::class)->in('GraphQL/GraphqlWithoutRestTest.php');

// Boot tests need the app booted against content that already exists on disk,
// so they get their own case instead of building fixtures per test.
pest()->extend(ExistingCollectionTestCase::class)->in('Boot');
