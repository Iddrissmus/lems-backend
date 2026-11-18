<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 10)]
class ChangePasswordController extends Controller
{
    #[Endpoint(title: 'Change your password', description: 'Requires the current password. Signs out every other device and keeps the current one.')]
    public function __invoke(ChangePasswordRequest $request, ChangePassword $change): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $change->execute(
            $user,
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['message' => 'Your password has been changed.']);
    }
}
