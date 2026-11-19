<?php

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Every feature test runs on a freshly migrated MySQL test database
// (lems_backend_test, set in phpunit.xml), never on lems_backend,
// with all roles and permissions already seeded.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * An active user holding the given role.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(Role $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Signs in a new user with the given role for the rest of the test.
 */
function actingAsRole(Role $role): User
{
    $user = userWithRole($role);
    Sanctum::actingAs($user);

    return $user;
}
