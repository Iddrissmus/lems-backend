# Guide for the frontend team

Everything you need to talk to the LEMS SCNMTC API. The complete, always-current list of endpoints and fields is in `openapi.json` (or the interactive page at `/docs/api` on staging). This page covers what the contract alone does not say.

## Basics

- Base URL: `<api host>/api/v1`
- Always send `Accept: application/json`. Send `Content-Type: application/json` when you send a body.
- Times are ISO 8601 in UTC (`2026-11-18T14:30:00+00:00`). The college is on Ghana time, which equals UTC, so no conversion is needed.
- Ids are 26-character ULIDs in lower case (`01j9z3k8q2v6x4n7m5r1t0w8yb`). Treat them as opaque strings and send them back exactly as received.

## Signing in

1. `POST /auth/login` with `{email, password, device_name?}`.
2. The answer contains `token`, `token_type` (`Bearer`), `expires_at` and the `user`.
3. Send `Authorization: Bearer <token>` on every other request.
4. `GET /me` returns the user with `roles` and `permissions`. Use `permissions` to decide which screens and buttons to show. The API checks permissions again on every request, so this is only for the UI.
5. `POST /auth/logout` ends the session on this device.

Tokens last 12 hours. After that, any call answers `401` with `code: "unauthenticated"`: send the person back to the sign-in page.

**Where to keep the token.** If your frontend is a server-rendered Laravel app, keep the token in the server-side session and call the API from the server. Do not put it in `localStorage` or in JavaScript-readable cookies. If the browser calls the API directly, tell us your origin so it can be allowed (see CORS below).

## Pages the frontend must provide

Emails sent by the API contain links to **your** pages:

| Page | Link in the email | What the page does |
|---|---|---|
| Accept invitation | `<frontend>/accept-invitation?token=...` | Asks for a new password, then calls `POST /auth/invitations/accept` with `{token, password, password_confirmation}`. Afterwards, send the person to sign in. |
| Reset password | `<frontend>/reset-password?token=...&email=...` | Asks for a new password, then calls `POST /auth/reset-password` with `{email, token, password, password_confirmation}`. |
| Forgot password | (linked from your sign-in page) | Calls `POST /auth/forgot-password` with `{email}`. It always answers `202`, whether or not the email exists. Show "If that email has an account, a link has been sent." |

There is no public sign-up. Accounts are created by an administrator, so the `/register` page should be removed or point to "contact the college".

## Errors

Every error body has the same three fields:

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] },
  "code": "validation_failed"
}
```

- Decide what to do from the HTTP status and `code`. Do not match on `message`; the wording can change.
- `errors` is always an object. It is empty unless the status is `422`.
- Show `errors.<field>` next to the matching form field.

Codes you should handle:

| Status | `code` | What to do |
|---|---|---|
| 401 | `unauthenticated` | Token missing or expired: go to sign in. |
| 401 | `invalid_credentials` | Wrong email or password (login only). |
| 403 | `forbidden` | Signed in but not allowed: show "no permission". |
| 403 | `account_disabled` | Sign the person out and explain. |
| 404 | `not_found` | Show "not found". |
| 409 | `conflict`, `last_super_admin` | Show the `message`; it is written for people for these. |
| 422 | `validation_failed` | Show `errors` per field. |
| 422 | `invalid_token` | An emailed link was already used or has expired: offer to request a new one. |
| 422 | `cannot_disable_self` | Show the `message`. |
| 429 | `too_many_requests` | Wait the number of seconds in the `Retry-After` header. Login locks an email for 15 minutes after 5 failed tries. |
| 500 | `server_error` | Show a generic "something went wrong". |

## Lists

List endpoints answer:

```json
{ "data": [ ... ], "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 25, "total": 130, "last_page": 6, "from": 1, "to": 25 } }
```

- Page with `?page=2&per_page=25`. `per_page` has a maximum (100).
- Filters and sorting are documented per endpoint (for example `/users?q=ama&status=active&role=lecturer&sort=-created_at`).

## Rate limits

- Public sign-in endpoints: 20 requests per minute per IP.
- Login: 5 failed tries per email, then 15 minutes locked.
- On `429`, read `Retry-After`.

## CORS

Only websites we list can call the API from a browser. Tell us the exact origin(s), for example `https://portal.example.edu.gh` (and a development origin such as `http://localhost:3000`). The browser sends no cookies to the API; authentication is the `Authorization` header only.

## Passwords

At least 10 characters with upper and lower case letters and a digit. Show this next to the password fields. The API answers `422` with `errors.password` if the rule is broken.

## Changes to the API

We tell you before a change is merged, with the diff of `openapi.json`. Inside `/api/v1` we do not remove or rename anything you use.
