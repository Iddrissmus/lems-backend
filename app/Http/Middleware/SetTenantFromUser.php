<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With tenancy on, the signed-in user decides the tenant for the whole
 * request. Use after auth:sanctum. With tenancy off it does nothing.
 */
class SetTenantFromUser
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->tenants->enabled() && $user instanceof User) {
            $this->tenants->set((int) $user->tenant_id);
        }

        return $next($request);
    }

    /** Leave nothing behind for the next request in the same process. */
    public function terminate(Request $request, Response $response): void
    {
        $this->tenants->forget();
    }
}
