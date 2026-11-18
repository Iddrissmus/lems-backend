<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/**
 * The audit trail is sensitive (it shows who did what, from which IP).
 * Only super_admin sees it for now, through the Gate::before bypass; no
 * other role holds `audit.view` until the college decides who should.
 * Nobody, super_admin included, can change or delete entries.
 */
class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AuditView->value);
    }
}
