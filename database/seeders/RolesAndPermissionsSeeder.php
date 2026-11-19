<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Permission as PermissionName;
use App\Domain\Identity\Enums\Role as RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every role and permission and links them. Safe to run again at
 * any time (deploys run it): it only adds or corrects, never deletes users.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions(array_map(fn (PermissionName $p) => $p->value, $roleName->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
