<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum's token, with one change: the owner is loaded without the tenant
 * scope. The token is how we find out who the user (and so the tenant) is,
 * so it cannot depend on a tenant already being known.
 */
class PersonalAccessToken extends SanctumToken
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return parent::tokenable()->withoutGlobalScopes();
    }
}
