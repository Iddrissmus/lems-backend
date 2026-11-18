<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, ResetPassword $reset): JsonResponse
    {
        $reset->execute(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['message' => 'Your password has been reset. Please sign in.']);
    }
}
