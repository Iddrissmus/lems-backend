<?php

namespace App\Http\Resources;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The signed-in user as returned by /me: a user plus everything the
 * frontend needs to show or hide screens. The API still enforces
 * permissions on every request; this list is only for the UI.
 *
 * @mixin User
 */
class CurrentUserResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'permissions' => $this->isSuperAdmin()
                ? Permission::values()
                : $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ]);
    }
}
