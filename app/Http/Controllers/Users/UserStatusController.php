<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\DisableUser;
use App\Domain\Identity\Actions\EnableUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Users', weight: 20)]
class UserStatusController extends Controller
{
    #[Endpoint(title: 'Disable an account', description: 'Requires permission users.disable. Signs the user out everywhere at once. You cannot disable yourself (422) or the last active super_admin (409).')]
    public function disable(Request $request, User $user, DisableUser $disableUser): UserResource
    {
        Gate::authorize('disable', $user);

        /** @var User $actor */
        $actor = $request->user();

        $disableUser->execute($user, $actor);

        return new UserResource($user->load('roles'));
    }

    #[Endpoint(title: 'Re-enable an account', description: 'Requires permission users.disable. Returns to active, or to invited if the person never accepted their invitation.')]
    public function enable(Request $request, User $user, EnableUser $enableUser): UserResource
    {
        Gate::authorize('disable', $user);

        /** @var User $actor */
        $actor = $request->user();

        $enableUser->execute($user, $actor);

        return new UserResource($user->load('roles'));
    }
}
