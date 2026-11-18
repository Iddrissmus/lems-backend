<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class AcceptInvitationController extends Controller
{
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
