<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\PasswordChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ChangePassword
{
    public function execute(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $current = $user->currentAccessToken();

        DB::transaction(function () use ($user, $newPassword, $current): void {
            $user->forceFill(['password' => $newPassword, 'must_change_password' => false])->save();

            // Sign out every other device; keep the one making this request.
            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->getKey()))
                ->delete();

            event(new PasswordChanged($user));
        });
    }
}
