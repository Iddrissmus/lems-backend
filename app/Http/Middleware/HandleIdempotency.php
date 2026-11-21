<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a write safe to retry. When a request carries an Idempotency-Key header:
 *
 * - first time: run it and remember the answer (encrypted) for 24 hours;
 * - same key, same request again: return the remembered answer with the header
 *   `Idempotent-Replayed: true` and do nothing a second time;
 * - same key, a different request: 422 idempotency_key_reused;
 * - same key while the first is still running: 409 request_in_progress.
 *
 * Only a successful (2xx) answer is remembered. A failure (validation, permission,
 * not found, a crash) releases the key so the client can fix the request and retry
 * with the same key. Keys belong to the signed-in user. Use after auth:sanctum and tenant.
 */
class HandleIdempotency
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if ($key === null || ! $user instanceof User || ! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_\-:.]{8,100}$/', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => ['The Idempotency-Key must be 8 to 100 letters, digits or - _ : . (a UUID is ideal).']]);
        }

        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $record = $this->claim($user, $key, $request, $hash);

        if (! $record->wasRecentlyCreated) {
            return $this->replay($record, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();

            throw $e;
        }

        // Laravel renders most errors into a response inside the pipeline, so check the status too.
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $record->delete();

            return $response;
        }

        $record->forceFill(['status' => $response->getStatusCode(), 'response' => (string) $response->getContent()])->save();

        return $response;
    }

    private function claim(User $user, string $key, Request $request, string $hash): IdempotencyKey
    {
        try {
            return IdempotencyKey::create([
                'user_id' => $user->id,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $hash,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::where('user_id', $user->id)->where('key', $key)->first();

            // An expired key is simply free again.
            if ($existing === null || $existing->isExpired()) {
                $existing?->delete();

                return IdempotencyKey::create([
                    'user_id' => $user->id,
                    'key' => $key,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'request_hash' => $hash,
                ]);
            }

            return $existing;
        }
    }

    private function replay(IdempotencyKey $record, string $hash): Response
    {
        if (! hash_equals($record->request_hash, $hash)) {
            throw new ApiException(422, 'idempotency_key_reused', 'This Idempotency-Key was already used for a different request. Use a new key for a new action.');
        }

        if ($record->status === null) {
            throw new ApiException(409, 'request_in_progress', 'This request is still being processed. Wait a moment and retry with the same key.');
        }

        return response((string) $record->response, $record->status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
