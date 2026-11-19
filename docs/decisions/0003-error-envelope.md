# 0003 - One error format for every endpoint

Date: 2026-11-18 | Status: Accepted | Decided by: Iddriss (from the project brief)

## Context

Laravel returns errors in several shapes depending on the kind of failure. The frontend would need a different handler for each, and could easily show raw technical text to students.

## Decision

Every error from `/api/*` has the body `{ "message", "errors", "code" }`:

- `message`: a sentence for people. Its wording can change.
- `errors`: always an object; field names mapped to lists of messages for validation errors, otherwise `{}`.
- `code`: a stable machine-readable word (`validation_failed`, `invalid_credentials`, `last_super_admin`, ...). The frontend matches on this.

It is produced in one place (`app/Support/Api/ErrorResponder.php`). Application code raises expected failures with `ApiException(status, code, message)`. Statuses follow the brief: 422 validation, 401 and 403 authentication and authorization, 404 not found, 409 conflict, 429 rate limited.

On 5xx the real message is never sent unless `APP_DEBUG` is on, and the error is still logged. Messages for 4xx errors from the framework (for example "No query results for model ...") are replaced by fixed wording so internals do not leak.

The OpenAPI document describes the same shape (`app/Support/Api/ErrorEnvelopeDocumentation.php`) and a test checks that every error response uses it.

## Alternatives considered

- **Laravel's defaults:** several shapes, leaks framework wording.
- **RFC 7807 problem+json in full:** more standard, but heavier than the college's frontend needs. The envelope is RFC 7807-style as the brief asked.

## Consequences

- One error handler in the frontend.
- New `code` values may be added; removing or renaming one is a breaking change.
