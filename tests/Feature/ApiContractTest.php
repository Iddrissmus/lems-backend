<?php

use App\Domain\Identity\Enums\Role;
use App\Models\User;

// The shapes agreed with the frontend team (docs/api/frontend-guide.md).
// If one of these fails, the contract changed: update the guide and tell them.

describe('health', function () {
    it('answers /health without a token', function () {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'version', 'time'])
            ->assertJsonPath('status', 'ok');
    });
});

describe('routes', function () {
    it('serves the docs at /docs and the spec at /docs.json', function () {
        expect(route('scramble.docs.ui', absolute: false))->toBe('/docs')
            ->and(route('scramble.docs.document', absolute: false))->toBe('/docs.json');
    });

    it('serves the password routes under /auth/password', function () {
        foreach (['forgot', 'reset'] as $action) {
            $this->postJson("/api/v1/auth/password/{$action}", [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/password/change', [])->assertStatus(401);
    });

    it('no longer serves the old password routes', function () {
        foreach (['forgot-password', 'reset-password', 'change-password'] as $old) {
            $this->postJson("/api/v1/auth/{$old}", [])->assertNotFound();
        }
    });
});

describe('success responses are wrapped in data', function () {
    it('wraps login, with the user, roles and permissions the UI needs', function () {
        $user = userWithRole(Role::Lecturer, ['email' => 'contract@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'contract@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->ulid)
            ->assertJsonPath('data.user.roles', ['lecturer'])
            ->assertJsonPath('data.user.permissions', [])
            ->assertJsonPath('data.user.must_change_password', false);
    });

    it('wraps /me, lists, roles and message-only answers', function () {
        actingAsRole(Role::SuperAdmin);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonStructure(['data' => ['id', 'roles', 'permissions']]);
        $this->getJson('/api/v1/users')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/v1/roles')->assertOk()->assertJsonStructure(['data', 'meta' => ['can_assign']]);
    });

    it('wraps the message-only answers', function () {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['message']]);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/password/change', [
                'current_password' => 'wrong',
                'password' => 'Another-Passw0rd-9',
                'password_confirmation' => 'Another-Passw0rd-9',
            ])->assertStatus(422)->assertJsonStructure(['message', 'errors', 'code']);
    });
});
