# Guide for the frontend team

Everything you need to talk to the LEMS SCNMTC API. The complete, always-current list of endpoints and fields is in `openapi.json` (or the interactive page at `/docs` on local and staging). A ready-made Postman collection is in `docs/api/postman/`. This page covers what the contract alone does not say. How to wire the two apps together (tunnel, settings, a joint test) is in `docs/frontend-integration.md`.

## Basics

- Base URL: `<api host>/api/v1`
- Always send `Accept: application/json`. Send `Content-Type: application/json` when you send a body.
- Times are ISO 8601 in UTC (`2026-11-18T14:30:00+00:00`). The college is on Ghana time, which equals UTC, so no conversion is needed.
- Ids are 26-character ULIDs in lower case (`01j9z3k8q2v6x4n7m5r1t0w8yb`). Treat them as opaque strings and send them back exactly as received.
- Money (from Phase 2 on) is an integer in pesewas with `currency: "GHS"`. Never a float.

## Successful responses

Every successful answer that has a body is wrapped in `data`:

```json
{ "data": { "id": "01j9...", "name": "Ama Mensah" } }
```

- A single record: `data` is an object.
- A list: `data` is an array, next to `links` and `meta` (see Lists below).
- A message-only answer (for example after changing a password): `data` is `{ "message": "..." }`.
- Extra information that is not part of the record goes in `meta` (for example `GET /roles` returns `meta.can_assign`).
- No body: `204` (for example sign out).
- Two infrastructure endpoints are **not** wrapped, on purpose, because monitors read them: `GET /health` and `GET /ready`.

## Signing in

1. `POST /auth/login` with `{email, password, device_name?}`.
2. The answer is `{ "data": { "token", "token_type": "Bearer", "expires_at", "user": { id, name, email, status, must_change_password, roles[], permissions[] } } }`.
3. Send `Authorization: Bearer <token>` on every other request.
4. `GET /me` returns the same user object (in `data`). Use `roles` and `permissions` to decide which screens and buttons to show. The API checks permissions again on every request, so this is only for the UI.
5. `POST /auth/logout` ends the session on this device (answers `204`).

**Forced password change.** If `user.must_change_password` is `true`, send the person to a "choose a new password" screen right away. Until they do, every endpoint except `GET /me`, `POST /auth/password/change` and `POST /auth/logout` answers `403` with `code: "password_change_required"`. `POST /auth/password/change` takes `{current_password, password, password_confirmation}` and clears the flag.

Tokens last 12 hours. After that, any call answers `401` with `code: "unauthenticated"`: send the person back to the sign-in page.

**Where to keep the token.** We recommend your Laravel app calls the API **from its server** and keeps the token in the server-side session. The token then never reaches the browser, and no CORS is needed. Do not put it in `localStorage` or in JavaScript-readable cookies. If the browser calls the API directly instead, tell us your origin so it can be allowed (see CORS below).

## Pages the frontend must provide

Emails sent by the API contain links to **your** pages:

| Page | Link in the email | What the page does |
|---|---|---|
| Accept invitation | `<frontend>/accept-invitation?token=...` | Asks for a new password, then calls `POST /auth/invitations/accept` with `{token, password, password_confirmation}`. Afterwards, send the person to sign in. |
| Reset password | `<frontend>/reset-password?token=...&email=...` | Asks for a new password, then calls `POST /auth/password/reset` with `{email, token, password, password_confirmation}`. |
| Forgot password | (linked from your sign-in page) | Calls `POST /auth/password/forgot` with `{email}`. It always answers `202`, whether or not the email exists. Show "If that email has an account, a link has been sent." |

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
| 403 | `password_change_required` | Send the person to the change-password screen (see Signing in). |
| 404 | `not_found` | Show "not found". |
| 409 | `conflict`, `last_super_admin` | Show the `message`; it is written for people for these. |
| 422 | `validation_failed` | Show `errors` per field. |
| 422 | `invalid_token` | An emailed link was already used or has expired: offer to request a new one. |
| 422 | `cannot_disable_self` | Show the `message`. |
| 422 | `registration_rejected` | One or more courses were refused; show each reason from `errors["course_offering_ids.N"]`. Nothing was registered |
| 422 | `registration_closed` | No registration window is open for the student |
| 422 | `idempotency_key_reused` | You sent the same `Idempotency-Key` for a different request; use a fresh key |
| 409 | `request_in_progress` | The same `Idempotency-Key` is still being processed; wait and retry with the same key |
| 423 | `grade_locked` | Marks were already submitted and are locked (arrives with the marks endpoints) |
| 429 | `too_many_requests` | Wait the number of seconds in the `Retry-After` header. Login locks an email for 15 minutes after 5 failed tries. |
| 500 | `server_error` | Show a generic "something went wrong". |

## Safe retries (`Idempotency-Key`)

Send a header `Idempotency-Key: <a fresh UUID>` on every `POST`, `PUT` or `DELETE` that changes something. If the connection drops and you send the **same request with the same key** again, you get the original answer back (header `Idempotent-Replayed: true`) and nothing happens twice. Use a new key for each new user action. Only successful answers are remembered, so after an error you can fix the request and retry with the same key. Keys last 24 hours.

## Registration endpoints

Registration windows, registering students, a student's own course registration and the reference lists (departments, programmes, cohorts, semesters, courses on offer) are described in `docs/api/demo-contract.md` and are in the OpenAPI document.

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

Needed only if JavaScript in the browser calls the API directly; calls from your Laravel server are not affected by CORS. Only websites we list may call from a browser. The list is the `FRONTEND_ORIGINS` setting (comma separated, never `*`); it defaults to `https://thescnmtc-lms.fly.dev`. Tell us any other exact origin, such as a development one (`http://localhost:3000`). The browser sends no cookies to the API; authentication is the `Authorization` header only.

## Roles

The roles that exist now: `super_admin`, `head_of_academics`, `academic_affairs`, `ict_admin`, `registrar`, `bursar`, `lecturer`, `student`. More (for example `clinical_coordinator`, `external_assessor`) arrive with their modules. A user can hold several roles. Always decide screens from `permissions`, not from role names, so a change of who may do what needs no frontend change.

## Health checks

`GET /health` (no token) answers `{ "status": "ok", "version", "time" }` while the app runs. `GET /ready` also checks the database and cache (`503` if one is down). Neither is wrapped in `data`.

## Passwords

At least 10 characters with upper and lower case letters and a digit. Show this next to the password fields. The API answers `422` with `errors.password` if the rule is broken.

## Changes to the API

We tell you before a change is merged, with the diff of `openapi.json`. Inside `/api/v1` we do not remove or rename anything you use.
