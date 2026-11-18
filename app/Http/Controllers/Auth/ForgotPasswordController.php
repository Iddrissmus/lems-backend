<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetLink $sendLink): JsonResponse
    {
        $sendLink->execute($request->string('email')->toString());

        // Same answer for every address, so accounts cannot be discovered.
        return response()->json([
            'message' => 'If that email belongs to an active account, a reset link has been sent.',
        ], 202);
    }
}
