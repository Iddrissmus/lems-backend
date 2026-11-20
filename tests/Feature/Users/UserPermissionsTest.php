<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

describe('who can manage accounts', function () {
    it('lets super_admin, head_of_academics and ict_admin list users', function (Role $role) {
        actingAsRole($role);

        $this->getJson('/api/v1/users')->assertOk();
    })->with([Role::SuperAdmin, Role::HeadOfAcademics, Role::IctAdmin]);

    it('forbids every other role', function (Role $role) {
        actingAsRole($role);

        $this->getJson('/api/v1/users')->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $this->postJson('/api/v1/users/invite', [
            'name' => 'X', 'email' => 'x@example.com', 'roles' => [Role::Student->value],
        ])->assertStatus(403);
    })->with([Role::AcademicAffairs, Role::Registrar, Role::Bursar, Role::Lecturer, Role::Student]);

    it('requires a token', function () {
        $this->getJson('/api/v1/users')->assertStatus(401);
    });
});

describe('/me', function () {
    it('returns roles and permissions for the UI', function () {
        $user = userWithRole(Role::HeadOfAcademics);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['head_of_academics'])
            ->assertJsonPath('data.permissions', collect(Permission::usersGroup())
                ->map->value->sort()->values()->all());
    });

    it('lists every permission for super_admin', function () {
        actingAsRole(Role::SuperAdmin);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['super_admin'])
            ->assertJsonPath('data.permissions', Permission::values());
    });

    it('gives a student no permissions', function () {
        actingAsRole(Role::Student);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.permissions', []);
    });
});

describe('roles list', function () {
    it('hides super_admin from head_of_academics', function () {
        actingAsRole(Role::HeadOfAcademics);

        $names = collect($this->getJson('/api/v1/roles')->assertOk()->json('data'))->pluck('name');

        expect($names)->not->toContain('super_admin')->toContain('lecturer');
    });

    it('shows every role to super_admin', function () {
        actingAsRole(Role::SuperAdmin);

        expect($this->getJson('/api/v1/roles')->json('data'))->toHaveCount(count(Role::cases()));
    });
});

it('seeds roles idempotently and gives head_of_academics only the user permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Spatie\Permission\Models\Role::count())->toBe(count(Role::cases()))
        ->and(Spatie\Permission\Models\Permission::count())->toBe(count(Permission::cases()))
        ->and(User::factory()->create()->hasRole('super_admin'))->toBeFalse();

    $head = Spatie\Permission\Models\Role::findByName('head_of_academics');
    expect($head->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::usersGroup())->map->value->sort()->values()->all());

    // ict_admin has the same account-management rights (owner's decision, 2026-11-20).
    $ict = Spatie\Permission\Models\Role::findByName('ict_admin');
    expect($ict->permissions->pluck('name')->sort()->values()->all())
        ->toBe($head->permissions->pluck('name')->sort()->values()->all());

    // academic_affairs holds nothing until the results module is built.
    expect(Spatie\Permission\Models\Role::findByName('academic_affairs')->permissions)->toHaveCount(0);
});
