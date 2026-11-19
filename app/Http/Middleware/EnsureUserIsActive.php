<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a disabled account that still holds a valid token.
 * Use after auth:sanctum.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->status->canLogin()) {
            throw new ApiException(403, 'account_disabled', 'This account is not active.');
        }

        return $next($request);
    }
}
