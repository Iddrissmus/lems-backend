<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 10)]
class AcceptInvitationController extends Controller
{
    #[Endpoint(title: 'Accept an invitation', description: 'Sets the password for an invited account and activates it. The token comes from the emailed link /accept-invitation?token=... (valid 72 hours, single use). No sign-in token is returned; the person signs in afterwards. Public endpoint.')]
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitation $accept): JsonResponse
    {
        $user = $accept->execute(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        // No token is issued here: the person signs in normally afterwards.
        return response()->json([
            'message' => 'Your account is ready. Please sign in.',
            'user' => new UserResource($user),
        ]);
    }
}
