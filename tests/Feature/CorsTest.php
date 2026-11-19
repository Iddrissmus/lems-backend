<?php

beforeEach(function () {
    config(['cors.allowed_origins' => ['https://portal.example.edu.gh']]);
});

it('answers a preflight from an allowed origin', function () {
    $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
        'HTTP_ORIGIN' => 'https://portal.example.edu.gh',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
    ])
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.edu.gh')
        ->assertHeader('Access-Control-Allow-Methods');
});

it('adds the allow-origin header to real responses for an allowed origin', function () {
    $this->getJson('/api/v1/ready', ['Origin' => 'https://portal.example.edu.gh'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.edu.gh');
});

it('never echoes a disallowed origin back', function () {
    // With one allowed origin the library answers with that origin, never the
    // caller's, so a browser on the other site still blocks the response.
    $actual = $this->getJson('/api/v1/ready', ['Origin' => 'https://evil.example.com']);
    $preflight = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($actual->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com')
        ->and($preflight->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com');
});

it('never allows cookies to be sent cross-origin', function () {
    $this->getJson('/api/v1/ready', ['Origin' => 'https://portal.example.edu.gh'])
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});
