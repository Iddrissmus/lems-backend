<?php

it('reports ready when database and cache work', function () {
    $this->getJson('/api/v1/ready')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ready',
            'checks' => ['database' => 'ok', 'cache' => 'ok'],
        ]);
});

it('reports unavailable with 503 when the database is down', function () {
    // A second connection pointing at a port nothing listens on.
    config([
        'database.connections.unreachable' => [
            ...config('database.connections.mysql'),
            'port' => 1,
        ],
        'database.default' => 'unreachable',
    ]);

    try {
        $this->getJson('/api/v1/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.database', 'fail');
    } finally {
        // Restore so the test cleanup rolls back on the real test connection.
        config(['database.default' => 'mysql']);
    }
});
