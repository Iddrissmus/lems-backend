<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\DisableUser;
use App\Domain\Identity\Actions\EnableUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserStatusController extends Controller
{
    public function disable(Request $request, User $user, DisableUser $disableUser): UserResource
    {
        Gate::authorize('disable', $user);

        /** @var User $actor */
        $actor = $request->user();

        $disableUser->execute($user, $actor);

        return new UserResource($user->load('roles'));
    }

    public function enable(Request $request, User $user, EnableUser $enableUser): UserResource
    {
        Gate::authorize('disable', $user);

        /** @var User $actor */
        $actor = $request->user();

        $enableUser->execute($user, $actor);

        return new UserResource($user->load('roles'));
    }
}
