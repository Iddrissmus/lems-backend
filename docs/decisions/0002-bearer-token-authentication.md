# 0002 - Bearer tokens for frontend authentication (D1)

Date: 2026-11-18 | Status: Provisional | Decided by: Iddriss (default accepted, to be confirmed with the frontend team)

## Context

The frontend must prove who the user is on every call. Laravel Sanctum offers two ways: cookie-based sessions for a single-page app (which require the frontend and API to share a parent domain) or personal access tokens sent in an `Authorization` header. The owner expects both to share a domain but is not sure yet. The frontend is a separate Laravel application.

## Decision

Use Sanctum **bearer tokens**. Login returns a token valid for 12 hours (`SANCTUM_TOKEN_EXPIRATION`, in minutes). Sanctum's cookie guard is turned off (`config/sanctum.php`, `guard => []`). Browsers never send cookies to the API, which also removes the need for CSRF protection.

## Alternatives considered

- **Cookie sessions (SPA mode):** simpler for the browser, but only works on a shared parent domain and needs CSRF and stateful-domain configuration. Not ruled out; it can be added later without changing the endpoints.

## Consequences

- Works whatever the domains turn out to be.
- The frontend is responsible for storing the token safely. The guide (`docs/api/frontend-guide.md`) recommends keeping it in the server-side session of the frontend app.
- A stolen token works until it expires or is revoked. Tokens are revoked on logout, password change, password reset and when an account is disabled.
- **Open:** confirm with the frontend team. If they prefer cookies, record a new decision that supersedes this one.
