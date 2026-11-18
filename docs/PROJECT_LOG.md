# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 1 - Foundation, step 3 (identity) built, waiting for owner review/merge
- Branch: `phase-1/identity` (stacked on `phase-1/errors-health`, merge that PR first)
- Done so far: step 1 skeleton (merged); step 2 error envelope and `/api/v1/ready` (PR open); step 3 identity
- Next: step 4 - roles and permissions (spatie), policies, first super_admin seeder, invite-user endpoint
- Waiting on owner: merge PRs for steps 2 and 3; confirm D1 (built as bearer tokens); share auth contract with frontend team; Redis decision

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

