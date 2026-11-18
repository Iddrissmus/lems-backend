<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Lists the roles the current user is allowed to hand out (for dropdowns). */
#[Group('Users', weight: 20)]
class RoleController extends Controller
{
    #[Endpoint(title: 'List assignable roles', description: 'Requires permission users.view. The roles the current user may hand out (super_admin is hidden from everyone but a super_admin). Use it to fill role pickers.')]
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = $request->user();

        $roles = collect(Role::cases())
            ->reject(fn (Role $role) => $role === Role::SuperAdmin && ! $actor->isSuperAdmin())
            ->map(fn (Role $role) => [
                'name' => $role->value,
                'label' => ucwords(str_replace('_', ' ', $role->value)),
            ])
            ->values();

        return response()->json([
            'data' => $roles,
            'can_assign' => $actor->isSuperAdmin() || $actor->can(Permission::UsersAssignRoles->value),
        ]);
    }
}
