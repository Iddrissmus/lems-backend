<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an account that was issued a password by someone else until the
 * user has chosen their own. Use after auth:sanctum. Routes the user needs
 * in order to fix this (/me, change password, logout) are left outside it.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            throw new ApiException(403, 'password_change_required', 'You must change your password before continuing.');
        }

        return $next($request);
    }
}
