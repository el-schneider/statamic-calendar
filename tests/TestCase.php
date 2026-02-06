<?php

namespace ElSchneider\StatamicCalendar\Tests;

use ElSchneider\StatamicCalendar\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
