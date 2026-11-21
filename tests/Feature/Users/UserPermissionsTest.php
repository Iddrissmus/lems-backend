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
            ->assertJsonPath('data.permissions', collect(Role::HeadOfAcademics->permissions())
                ->map->value->sort()->values()->all());
    });

    it('lists every permission for super_admin', function () {
        actingAsRole(Role::SuperAdmin);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['super_admin'])
            ->assertJsonPath('data.permissions', Permission::values());
    });

    it('gives a student the permissions to register for courses and read their own results', function () {
        actingAsRole(Role::Student);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.permissions', ['registration:self', 'results:view-own']);
    });

    it('gives a lecturer the permissions to teach, enter and submit marks', function () {
        actingAsRole(Role::Lecturer);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.permissions', ['courses:teach', 'marks:enter', 'marks:submit']);
    });

    it('gives a bursar no permissions yet', function () {
        actingAsRole(Role::Bursar);

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

it('seeds roles idempotently and gives each role exactly the permissions Role::permissions() lists', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Spatie\Permission\Models\Role::count())->toBe(count(Role::cases()))
        ->and(Spatie\Permission\Models\Permission::count())->toBe(count(Permission::cases()))
        ->and(User::factory()->create()->hasRole('super_admin'))->toBeFalse();

    foreach (Role::cases() as $role) {
        $stored = Spatie\Permission\Models\Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
        $expected = collect($role->permissions())->map->value->sort()->values()->all();

        expect($stored)->toBe($expected, $role->value);
    }
});

it('gives head_of_academics and ict_admin the account-management permissions, plus their own academic ones', function () {
    $users = collect(Permission::usersGroup())->map->value;

    $head = collect(Role::HeadOfAcademics->permissions())->map->value;
    $ict = collect(Role::IctAdmin->permissions())->map->value;

    expect($users->diff($head))->toBeEmpty()
        ->and($users->diff($ict))->toBeEmpty()
        ->and($head->all())->toContain('students:view', 'registration:manage')->not->toContain('accounts:create')
        ->and($ict->all())->toContain('accounts:create', 'accounts:reset-password', 'students:view')->not->toContain('registration:manage');
});

it('keeps the bursar free of academic permissions until their module exists, and gives the others only what they need', function () {
    expect(Role::Bursar->permissions())->toBe([])
        ->and(collect(Role::Lecturer->permissions())->map->value->all())->toBe(['courses:teach', 'marks:enter', 'marks:submit'])
        ->and(collect(Role::AcademicAffairs->permissions())->map->value->all())->toBe(['students:view', 'academics:dashboard:view'])
        ->and(collect(Role::HeadOfAcademics->permissions())->map->value->all())->toContain('academics:dashboard:view', 'results:release')
        ->and(collect(Role::Student->permissions())->map->value->all())->toBe(['registration:self', 'results:view-own']);
});

it('lets only the head of academics release results', function () {
    $holders = collect(Role::cases())->filter(fn (Role $role) => in_array(Permission::ResultsRelease, $role->permissions(), true));

    expect($holders->map->value->values()->all())->toBe(['head_of_academics']);
});
