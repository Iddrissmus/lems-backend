<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\AssignRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AssignRolesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UserRolesController extends Controller
{
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
