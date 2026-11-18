# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 1 - Foundation, step 5 (audit log) built, waiting for owner review/merge
- Branch: `phase-1/audit` (stacked: errors-health, identity, roles, audit; merge in that order)
- Done so far: step 1 skeleton (merged); step 2 error envelope and readiness; step 3 identity; step 4 roles and user management; step 5 CORS and audit log
- Next: step 6 - OpenAPI docs (Scramble), architecture, ERD and ADR docs
- Waiting on owner: merge PRs for steps 2 to 5 in order; confirm D1 (built as bearer tokens); share the endpoints with the frontend team and ask for their origin(s) for `CORS_ALLOWED_ORIGINS`; Redis decision

## Decisions (D1-D7 from setup.md)
| # | Decision | Status | Answer | ADR |
|---|---|---|---|---|
| D1 | Frontend auth | Provisional | Built as Sanctum bearer tokens (works on any domain). Cookies can be added later if the frontend shares a parent domain | - |
| D2 | Production server | Open | Not needed until staging deploy | - |
| D3 | Frontend payload shapes | Open | Ask frontend team | - |
| D4 | Data migration | Open | None assumed | - |
| D5 | Grading scale | Open | Must come from the college | - |
| D6 | SMS provider | Open | Email first, SMS behind interface | - |
| D7 | Repo host | Decided | GitHub | - |

## Log

### 2026-11-18 - Setup and environment choices
**Branch:** `main` | **Phase:** 0 - Setup
**Done:** read the brief; checked tooling (PHP 8.5.12, Composer 2.10.3, Docker 29.8, WSL; XAMPP MySQL listening on 3306); created `CLAUDE.md`, the `/log-progress` skill and this log.
**Decided:** run PHP locally on 8.5; use XAMPP MySQL with database `lems_backend`; repo on GitHub; commits are short with no co-author line. Redis not decided: nothing is listening on 6379 and phpredis is not enabled.
**Needs owner:** Redis choice (skip for now, or Docker on 6379); D1; go-ahead to start Phase 1.
**Next:** Phase 1 step 1, project skeleton, once approved.

### 2026-11-18 - Phase 1 step 1: project skeleton
**Branch:** `phase-1/skeleton` | **Phase:** 1 - Foundation
**Done:** scaffolded Laravel 13 (PHP 8.5 locally, requires 8.3+), API only: removed web routes, views and frontend tooling; API served under `/api/v1` (`routes/api.php`, `bootstrap/app.php`); health check at `/up`. Added Pest 4, Larastan (level 6, `phpstan.neon`), Pint. `composer ci` runs Pint, Larastan and Pest. Pre-push hook in `.githooks/pre-push` (enable with `git config core.hooksPath .githooks`). Tests run on MySQL database `lems_backend_test` (created in XAMPP) so they match production. `.env.example` points at XAMPP MySQL. README with Windows setup.
**Decided:** Laravel 13 chosen over the brief's 11/12 (newest, longest support; owner approved). No docker-compose for now since MySQL comes from XAMPP. Cache, queue and session use database drivers; `REDIS_CLIENT=predis` is set but Predis is not installed yet. App timezone stays UTC; Africa/Accra conversion is left to display logic.
**Needs owner:** review and commit; confirm D1 when known.
**Next:** step 2 - error handling envelope and readiness endpoint.

### 2026-11-18 - Phase 1 step 2: error envelope and readiness
**Branch:** `phase-1/errors-health` | **Phase:** 1 - Foundation
**Done:** every API error now returns `{message, errors, code}` (`app/Support/Api/ErrorResponder.php`, registered in `bootstrap/app.php`). Codes: `validation_failed` 422, `unauthenticated` 401, `forbidden` 403, `not_found` 404, `method_not_allowed` 405, `conflict` 409, `too_many_requests` 429 (keeps Retry-After), `server_error` 500+. `errors` is always an object. Unexpected errors never leak internals unless `APP_DEBUG` is on, and are still logged. Added `GET /api/v1/ready` (database and cache probe, 200 or 503) via `ReadinessController` and `ReadinessCheck`; liveness stays at `/up`. 14 Pest tests pass.
**Decided:** only 409 and 422 keep application-written messages; other HTTP errors use fixed messages so framework wording does not leak. Readiness response lists check names with ok/fail only, details go to the log.
**Needs owner:** review and commit. Share the error format with the frontend team.
**Next:** step 3 - identity. Needs D1 (bearer tokens vs cookies) settled first.

### 2026-11-18 - Phase 1 step 3: identity
**Branch:** `phase-1/identity` | **Phase:** 1 - Foundation
**Done:** users table (public `ulid` id, `status` invited/active/disabled, nullable password, soft deletes, `last_login_at`), `invitations` table (only a sha256 of the emailed token is stored), Sanctum bearer tokens. Endpoints under `/api/v1`: `POST auth/login`, `POST auth/logout`, `GET me`, `POST auth/forgot-password`, `POST auth/reset-password`, `POST auth/change-password`, `POST auth/invitations/accept`. Business logic is in `app/Domain/Identity/Actions/*`; controllers are thin; the new `ApiException` carries its own envelope `code` (`invalid_credentials`, `invalid_token`, `account_disabled`). `InviteUser` and `InvitationNotification` exist; the HTTP endpoint to invite comes with roles in step 4. 52 tests pass.
**Decided (owner can change any of these):**
- Self-registration stays off; accounts are created by invitation only (assumption from the brief, still unconfirmed).
- D1 built as bearer tokens, token lifetime 12 hours (`SANCTUM_TOKEN_EXPIRATION`, in minutes).
- Lockout: 5 failed logins per email in 15 minutes gives 429 with Retry-After. It is keyed on the email, so known and unknown addresses behave the same (no account discovery). Public auth routes are also limited to 20 requests per minute per IP.
- Password policy: at least 10 characters with upper and lower case and a digit; production also rejects passwords found in breaches. Not in the brief, please confirm.
- Invitation links last 72 hours, work once, and the newest link wins. Reset links last 60 minutes.
- Reset and invitation emails link to `FRONTEND_URL` (`/reset-password`, `/accept-invitation`); the frontend team must build those two pages.
- Resetting or changing a password signs out other devices.
- Email verification is not required (invited users are verified by opening the link).
- Larastan: enabled `parseModelCastsMethod` and `treatPhpDocTypesAsCertain: false` (Sanctum may return a TransientToken).
**Needs owner:** review; confirm password policy and token lifetime; send the frontend team the auth endpoints and the two email-link pages.
**Next:** step 4 - roles and permissions, policies, first super_admin, invite endpoint.

### 2026-11-18 - Phase 1 step 4: roles, permissions and user management
**Branch:** `phase-1/roles` | **Phase:** 1 - Foundation
**Done:** `spatie/laravel-permission` with the six roles as the `Role` enum and permissions as the `Permission` enum (`users.view`, `users.invite`, `users.update`, `users.disable`, `users.assign-roles`). `RolesAndPermissionsSeeder` is safe to re-run on every deploy. `UserPolicy` decides who can do what; `super_admin` passes every check through `Gate::before`. New endpoints (all need a token): `GET roles`, `GET users` (search `q`, filters `status` and `role`, `sort`, `per_page` up to 100, page-based), `POST users/invite`, `GET/PATCH users/{id}`, `POST users/{id}/resend-invitation`, `PUT users/{id}/roles`, `POST users/{id}/disable`, `POST users/{id}/enable`. `/me` now returns roles and permissions for the UI. Business logic is in Actions; role changes and disabling run in DB transactions. Events `UserInvited`, `UserRolesChanged`, `UserStatusChanged` are dispatched now; step 5 adds the audit listeners. `php artisan lems:create-super-admin` creates the first admin (password prompted, never in code). Demo users (`<role>@demo.test`) are seeded in the local environment only. 98 tests pass.
**Decided (owner approved the first two):**
- `super_admin` and `head_of_academics` can invite and manage accounts; only `super_admin` can assign the `super_admin` role or touch a super_admin account.
- Other roles start with no permissions; each module grants its own when built (e.g. registrar in Phase 2), once the college confirms the rules.
- Users can hold several roles at once.
- Safety rules: you cannot disable yourself, and the last active super_admin can never be disabled or demoted (409 `last_super_admin`).
- Disabling an account signs it out everywhere immediately.
- Re-enabling someone who never accepted their invitation returns them to "invited", not "active".
- The public id is the ULID; numeric ids are never accepted by the API.
**Needs owner:** review; confirm whether `head_of_academics` should really manage all accounts (including students); send the frontend team the new endpoints.
**Next:** step 5 - audit log.

### 2026-11-18 - Phase 1 step 5: CORS and audit log
**Branch:** `phase-1/audit` | **Phase:** 1 - Foundation
**Done:**
- CORS (`config/cors.php`): only the origins in `CORS_ALLOWED_ORIGINS` (comma separated, default `http://localhost:3000`) may call `/api/*` from a browser; no cookies; `Retry-After` exposed. Needed only if the frontend's JavaScript calls the API directly from the browser; a server-side frontend does not need it.
- Audit log: append-only `audit_logs` table (public ULID, event, actor, subject type and id, before and after as JSON, IP, user agent, time). The single writer is `AuditLogger`; `AuditSubscriber` turns domain events into entries. Recorded: login, failed login, lockout (once, when it starts), logout, password change, password reset, invitation accepted, user invited, name changed, roles changed (before and after), disabled, enabled. Passwords and tokens are never stored.
- Atomic: actions dispatch their events inside the same database transaction as the change, so a change and its audit entry are saved together or not at all (tested by making the audit write fail).
- Immutable: the model refuses update and delete. `GET /api/v1/audit-logs` is read-only, newest first, filters `event` (exact or `user.*` groups), `actor`, `subject_type`, `subject_id`, `from`, `to`, up to 100 per page. Only `super_admin` may read it (new permission `audit.view`, held by no other role).
- 136 tests pass.
**Decided (owner can change):**
- Audit reading is super_admin only until the college says who else may see it.
- Entries are kept indefinitely; no pruning.
**Known limits / to do at deployment:**
- A raw SQL `UPDATE`/`DELETE` bypasses the model rule. In production the app's MySQL user must be denied UPDATE and DELETE on `audit_logs` (to go into `docs/deployment.md` in step 7).
- Listeners that send email or call outside services must be queued and added separately, never to `AuditSubscriber`, so an outage cannot block saving a change.
**Needs owner:** review; send the frontend team the origin(s) to put in `CORS_ALLOWED_ORIGINS`.
**Next:** step 6 - OpenAPI docs, architecture, ERD and ADR docs.

