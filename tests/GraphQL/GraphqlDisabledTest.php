<?php

declare(strict_types=1);

test('does not register calendar occurrences when the addon GraphQL opt-in is disabled', function () {
    $this->postJson('/graphql', ['query' => '{ calendarOccurrences { total } }'])
        ->assertOk()
        ->assertJsonPath('errors.0.message', 'Cannot query field "calendarOccurrences" on type "Query".');
});
