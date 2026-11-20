<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantNotResolved;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Put this on every model whose table has a tenant_id (all domain tables).
 *
 * - Every query is limited to the current tenant (TenantScope).
 * - A new row gets the current tenant automatically.
 * - A row can never be created for, or moved to, a different tenant.
 *
 * To look across tenants on purpose (reports for the platform owner) use
 * Model::withoutGlobalScope(TenantScope::class) and make that visible in review.
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = app(TenantContext::class)->id();
            $given = $model->getAttribute('tenant_id');

            if ($given === null) {
                $model->setAttribute('tenant_id', $current ?? throw new TenantNotResolved('No tenant is in context, so this record cannot be saved.'));

                return;
            }

            if ($current !== null && (int) $given !== $current) {
                throw new TenantNotResolved('A record cannot be created for a different tenant than the current one.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new TenantNotResolved('A record cannot be moved to another tenant.');
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
