<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Users', weight: 20)]
class UserPasswordResetController extends Controller
{
    #[Endpoint(title: 'Issue a new temporary password', description: 'Requires accounts:reset-password. For an active account whose owner is locked out. The answer carries meta.temporary_password, shown ONCE; the person must change it at first sign-in and is signed out everywhere. You cannot do this for yourself (change your own password instead) or for a super_admin unless you are one. The audit log records that it happened, never the password.')]
    public function __invoke(Request $request, User $user, ResetUserPassword $reset): UserResource
    {
        Gate::authorize('resetPassword', $user);

        /** @var User $actor */
        $actor = $request->user();

        $temporaryPassword = $reset->execute($user, $actor);

        return (new UserResource($user->load('roles')))->additional(['meta' => ['temporary_password' => $temporaryPassword]]);
    }
}
