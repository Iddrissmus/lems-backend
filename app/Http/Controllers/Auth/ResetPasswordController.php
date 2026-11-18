<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 10)]
class ResetPasswordController extends Controller
{
    #[Endpoint(title: 'Reset a forgotten password', description: 'Sets a new password using the emailed token (valid 60 minutes, single use) and signs the user out on every device. Public endpoint.')]
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
