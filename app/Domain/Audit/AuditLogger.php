<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The single place that writes audit entries.
 *
 * Callers pass only the facts worth keeping (role names, a status, an
 * email). Never pass passwords, tokens or whole models: whatever goes in
 * `before` and `after` is stored in clear text and shown to auditors.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditEvent $event,
        ?User $actor = null,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'event' => $event->value,
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getAttribute('ulid'),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() === null ? null : Str::limit($request->userAgent(), 255, ''),
        ]);
    }
}
