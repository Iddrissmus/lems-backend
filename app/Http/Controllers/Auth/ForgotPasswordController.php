<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 10)]
class ForgotPasswordController extends Controller
{
    #[Endpoint(title: 'Request a password reset email', description: 'Always answers 202, whether or not the email has an account, so accounts cannot be discovered. Active accounts get an email linking to the frontend page /reset-password?token=...&email=.... Public endpoint.')]
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetLink $sendLink): JsonResponse
    {
        $sendLink->execute($request->string('email')->toString());

        // Same answer for every address, so accounts cannot be discovered.
        return response()->json(['data' => [
            'message' => 'If that email belongs to an active account, a reset link has been sent.',
        ]], 202);
    }
}
