<?php

namespace App\Support\Api;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\DocumentTransformers\CleanupUnusedResponseReferencesTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Makes the generated OpenAPI document describe our real error format.
 *
 * Scramble documents errors the way stock Laravel returns them
 * ({message} or {message, errors}). This API always answers
 * {message, errors, code} (see ErrorResponder), so every 4xx/5xx
 * response is rewritten to point at one shared `ErrorResponse` schema.
 */
class ErrorEnvelopeDocumentation implements DocumentTransformer
{
    /** @var array<int, string> */
    private const DESCRIPTIONS = [
        400 => 'Bad request. code: bad_request',
        401 => 'Not signed in, or the token is missing, invalid or expired. code: unauthenticated (or invalid_credentials on login)',
        403 => 'Signed in but not allowed. code: forbidden (or account_disabled, password_change_required)',
        404 => 'Not found. code: not_found',
        405 => 'Method not allowed. code: method_not_allowed',
        409 => 'Conflicts with the current state. code: conflict (or last_super_admin, request_in_progress, already_submitted, not_submitted, no_grade_scale)',
        422 => 'Validation failed; see `errors` per field. code: validation_failed (or invalid_token, cannot_disable_self, registration_rejected with a reason per course, registration_closed, idempotency_key_reused, marks_incomplete with the missing students in errors.marks)',
        423 => 'Marks already submitted are locked; only an authorised correction can change them. code: grade_locked',
        429 => 'Too many requests; wait `Retry-After` seconds. code: too_many_requests',
        500 => 'Server error, nothing leaked. code: server_error',
    ];

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $errors = (new ObjectType)
            ->addProperty('message', (new StringType)->setDescription('Human readable explanation. Wording may change, do not match on it.'))
            ->addProperty('errors', (new ObjectType)
                ->additionalProperties((new ArrayType)->setItems(new StringType))
                ->setDescription('Field name to list of messages. An empty object unless the error is a validation error.'))
            ->addProperty('code', (new StringType)->setDescription('Stable machine readable code. Safe to match on.'))
            ->setRequired(['message', 'errors', 'code']);

        $reference = $document->components->addSchema('ErrorResponse', Schema::fromType($errors));

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $index => $existing) {
                    // Responses are a plain list; a component reference (e.g. the
                    // stock ValidationException) must be resolved to read its code.
                    $resolved = $existing instanceof Reference ? $existing->resolve() : $existing;
                    $status = (int) ($resolved->code ?? 0);

                    if ($status < 400) {
                        continue;
                    }

                    $response = Response::make($status)
                        ->setDescription(self::DESCRIPTIONS[$status] ?? 'Error')
                        ->setContent('application/json', Schema::fromType($reference));

                    if ($status === 429) {
                        $response->addHeader('Retry-After', new Header(
                            description: 'Seconds to wait before trying again.',
                            schema: Schema::fromType(new StringType),
                        ));
                    }

                    $operation->responses[$index] = $response;
                }
            }
        }

        // Nothing points at Scramble's stock error components any more, so
        // run its own clean-up again to drop them from the document.
        (new CleanupUnusedResponseReferencesTransformer)->handle($document, $context);
    }
}
