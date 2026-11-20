<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\LoginUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\CurrentUserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 10)]
class LoginController extends Controller
{
    #[Endpoint(title: 'Sign in', description: 'Exchange email and password for a bearer token (valid 12 hours). The user comes back with roles, permissions and must_change_password. Five failed attempts for the same email lock it for 15 minutes (429). Public endpoint.')]
    public function __invoke(LoginRequest $request, LoginUser $login): JsonResponse
    {
        $token = $login->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString() ?: 'api',
        );

        $user = $token->accessToken->tokenable;

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new CurrentUserResource($user->load('roles')),
        ]]);
    }
}
