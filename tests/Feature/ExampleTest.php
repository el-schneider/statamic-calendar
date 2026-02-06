<?php

declare(strict_types=1);

it('resolves the service provider', function () {
    expect(config('statamic-calendar'))->toBeArray();
});
