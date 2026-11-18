<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\AssignRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AssignRolesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Facades\Gate;

#[Group('Users', weight: 20)]
class UserRolesController extends Controller
{
    #[Endpoint(title: 'Replace a user\'s roles', description: 'Requires permission users.assign-roles. Sends the complete set of roles the user should have. Only super_admin can grant or change super_admin. The last active super_admin can never lose the role (409 last_super_admin).')]
    public function update(AssignRolesRequest $request, User $user, AssignRoles $assignRoles): UserResource
    {
        $roles = $request->roles();

        Gate::authorize('assignRoles', [$user, $roles]);

        /** @var User $actor */
        $actor = $request->user();

        $assignRoles->execute($user, $roles, $actor);

        return new UserResource($user->load('roles'));
    }
}
