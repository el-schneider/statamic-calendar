<?php

declare(strict_types=1);

test('serves calendar occurrences when the REST API is disabled', function () {
    $this->postJson('/graphql', ['query' => '{ calendarOccurrences { total } }'])
        ->assertOk()
        ->assertJsonMissingPath('errors')
        ->assertJsonPath('data.calendarOccurrences.total', 0);
});
