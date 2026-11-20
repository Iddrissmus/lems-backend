<?php

use App\Domain\Identity\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
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

function tenancyContext(): TenantContext
{
    return app(TenantContext::class);
}

function newTenant(string $slug): Tenant
{
    return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug]);
}

/**
 * A request as the owner of a real token. Real requests start with a fresh app;
 * inside one test the guard would remember the previous caller, so reset it.
 */
function apiAs(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function defaultTenantId(): int
{
    return (int) Tenant::where('slug', 'scnmtc')->value('id');
}
