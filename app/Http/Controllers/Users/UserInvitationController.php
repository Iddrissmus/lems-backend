<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\InviteUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserInvitationController extends Controller
{
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
