<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Events\RegistrationWindowClosed;
use App\Models\RegistrationWindow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes a window early. Closing one that is already closed (by time or by
 * staff) does nothing and is not an error.
 */
class CloseRegistrationWindow
{
    public function execute(RegistrationWindow $window, User $actor): RegistrationWindow
    {
        if ($window->status() === RegistrationWindow::CLOSED) {
            return $window;
        }

        return DB::transaction(function () use ($window, $actor): RegistrationWindow {
            $window->forceFill(['closed_at' => now(), 'closed_by' => $actor->id])->save();

            event(new RegistrationWindowClosed($window, $actor));

            return $window;
        });
    }
}
