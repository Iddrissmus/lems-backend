<?php

use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use App\Support\Http\ProxyTrust;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Testing\TestResponse;

// Behind a tunnel or reverse proxy the API must see the real client, but only
// when the owner switched that on (TRUSTED_PROXIES).

afterEach(fn () => TrustProxies::flushState());

function throughProxy(): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->postJson(
        '/api/v1/auth/login',
        ['email' => 'proxy@example.com', 'password' => 'wrong'],
        ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'],
    );
}

it('ignores forwarded headers by default', function () {
    throughProxy()->assertStatus(401);

    expect(AuditLog::where('event', 'auth.login_failed')->latest('id')->value('ip_address'))->toBe('10.1.1.1');
});

it('records the real client IP when the proxy is trusted', function () {
    ProxyTrust::configure('*');

    throughProxy()->assertStatus(401);

    expect(AuditLog::where('event', 'auth.login_failed')->latest('id')->value('ip_address'))->toBe('203.0.113.7');
});

it('trusts only the listed proxies', function () {
    ProxyTrust::configure('192.168.0.0/16');
    throughProxy();
    expect(AuditLog::where('event', 'auth.login_failed')->latest('id')->value('ip_address'))->toBe('10.1.1.1');

    TrustProxies::flushState();
    ProxyTrust::configure('10.0.0.0/8, 192.168.1.1');
    throughProxy();
    expect(AuditLog::where('event', 'auth.login_failed')->latest('id')->value('ip_address'))->toBe('203.0.113.7');
});

it('generates https links behind a trusted proxy', function () {
    ProxyTrust::configure('*');
    actingAsRole(Role::SuperAdmin);

    $links = $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
        ->getJson('/api/v1/users?per_page=1', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'api.example.com'])
        ->assertOk()->json('links');

    expect($links['first'])->toStartWith('https://api.example.com/');
});
