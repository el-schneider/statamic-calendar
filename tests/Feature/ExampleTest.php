<?php

it('resolves the service provider', function () {
    expect(config('statamic-calendar'))->toBeArray();
});
