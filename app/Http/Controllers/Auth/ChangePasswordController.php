<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ChangePasswordController extends Controller
{
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
