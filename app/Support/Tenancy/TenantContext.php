<?php

namespace App\Support\Tenancy;

use App\Exceptions\TenantNotResolved;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Says which tenant (college) the current request works for.
 *
 * - Tenancy off (default): always the default tenant.
 * - Tenancy on: the tenant set for this request (from the signed-in user, see
 *   the SetTenantFromUser middleware). If none is set the answer is null and
 *   the scope then returns nothing: unknown means no access, never all access.
 *
 * Registered as a singleton, so the whole request sees the same answer.
 */
class TenantContext
{
    private ?int $explicit = null;

    private ?int $defaultId = null;

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    public function id(): ?int
    {
        if ($this->explicit !== null) {
            return $this->explicit;
        }

        return $this->enabled() ? null : $this->defaultId();
    }

    public function set(Tenant|int $tenant): void
    {
        $this->explicit = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;
    }

    public function forget(): void
    {
        $this->explicit = null;
    }

    /**
     * Runs a callback as another tenant, then puts the previous one back.
     * For seeders, console commands and tests; never for request handling.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = $this->explicit;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->explicit = $previous;
        }
    }

    private function defaultId(): int
    {
        // A plain query on purpose: Tenant has no scope, and this must never recurse.
        $this->defaultId ??= (int) DB::table('tenants')->where('slug', config('tenancy.default_slug'))->value('id');

        if ($this->defaultId === 0) {
            $this->defaultId = null;

            throw new TenantNotResolved('The default tenant does not exist. Run the migrations.');
        }

        return $this->defaultId;
    }
}
