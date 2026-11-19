<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\InviteUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Users', weight: 20)]
class UserInvitationController extends Controller
{
    #[Endpoint(title: 'Invite a user', description: 'Requires permission users.invite. Creates the account in status invited, assigns the roles and emails a 72 hour link. Only super_admin can invite someone as super_admin. 409 if the email already has an active account.')]
    public function store(InviteUserRequest $request, InviteUser $inviteUser): JsonResponse
    {
        $roles = $request->roles();

        Gate::authorize('invite', [User::class, $roles]);

        /** @var User $actor */
        $actor = $request->user();

        $invitation = $inviteUser->execute(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $roles,
            $actor,
        );

        return (new UserResource($invitation->user->load('roles')))
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint(title: 'Re-send an invitation', description: 'Requires permission users.invite. Only for accounts that have not accepted yet; the previous link stops working.')]
    public function resend(Request $request, User $user, InviteUser $inviteUser): JsonResponse
    {
        $roles = $user->getRoleNames()->all();

        Gate::authorize('invite', [User::class, $roles]);

        if ($user->status !== UserStatus::Invited) {
            throw new ApiException(409, 'conflict', 'Only accounts that have not accepted their invitation can be re-invited.');
        }

        /** @var User $actor */
        $actor = $request->user();

        $inviteUser->execute($user->name, $user->email, $roles, $actor);

        return response()->json(['message' => 'A new invitation has been sent.'], 202);
    }
}
