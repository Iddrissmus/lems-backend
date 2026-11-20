# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 1 - Foundation, now aimed at the **Slice 0 demo on Monday 23 Nov 2026** (scope: `updated scope.md`, backlog PDF in the repo root, both untracked and kept out of git)
- Branch: `phase-1/auth-contract` (off updated `main`, 7 commits, not pushed yet). Steps 1 to 6 are merged into `main` (PR #3)
- Done so far: steps 1 to 6 (skeleton, errors and readiness, identity, roles, CORS and audit log, API docs); on the current branch: forced password change, roles `academic_affairs` and `ict_admin`, contract alignment (`{data, meta}`, `/auth/password/*`, `/health`, `FRONTEND_ORIGINS`, docs at `/docs`), Postman collection, ADRs 0008 and 0009, CHANGELOG and handover checklist. 162 tests pass; live-tested end to end and with Postman's engine (see the 2026-11-20 testing entry)
- Next (Slice 0 plan): `phase-1/audit-hardening` (MySQL triggers on `audit_logs`, restricted `lems_app` DB user), `phase-1/tenancy` (tenants, tenant scope, switched off); then academic model, marks, locking (423 `grade_locked`) and corrections; then fees and announcements; then seeders, tunnel, `docs/demo.md`, `docs/frontend-integration` content. Cut order if time runs short: announcement targeting, corrections, fees
- Waiting on owner: push and merge `phase-1/auth-contract`; test with the Postman collection (`docs/manual-testing.md` Part 0b); answers from the frontend engineer (see "Needs owner" below); whether `updated scope.md` replaces `CLAUDE.md`; production database engine (note: XAMPP here is MariaDB 10.4.32, not MySQL 8, see ADR 0006); server details (D2) are not needed for the demo because it runs through a tunnel

## Decisions (D1-D15 from setup.md and the Slice 0 scope)
| # | Decision | Status | Answer | ADR |
|---|---|---|---|---|
| D1 | Frontend auth | Provisional | Built as Sanctum bearer tokens (works on any domain). Cookies can be added later if the frontend shares a parent domain | - |
| D2 | Production server | Open (demo decided) | Slice 0 demo runs on the owner's laptop behind a tunnel (Cloudflare Tunnel or ngrok), synthetic data only. Production hosting still open | - |
| D3 | Frontend payload shapes | Open | Ask frontend team | - |
| D4 | Data migration | Open | None assumed | - |
| D5 | Grading scale | Open (demo workaround) | Real scale and GPA/CGPA metric must come from the college. The demo uses a clearly labelled synthetic scale held as configuration, with no GPA | - |
| D6 | SMS provider | Open | Email first, SMS behind interface | - |
| D7 | Repo host | Decided | GitHub | - |
| D8 | Payment gateway | Open | Adapter interface with a fake driver first; manual receipts come first (Phase 2) | - |
| D9 | MFA method | Open | TOTP by default (after Slice 0) | - |
| D10 | Full user-group list (Schedule 1) | Open | 8 of the scope's 13 roles built; the rest added with their modules | [0005](decisions/0005-roles-and-permissions.md) |
| D11 | Retention and grace periods | Open | Configurable, nothing hard-coded | - |
| D12 | Evaluation minimum responses | Open | Config value `EVALUATION_MIN_RESPONSES`, number agreed with Academic Affairs | - |
| D13 | Meaning of BI requirement (FR-12.1) | Open | Fixed aggregate endpoints until clarified | - |
| D14 | Lecturer identity in contestations | Open | Ask before building | - |
| D15 | Capacity ceiling and support hours | Open | Ask, record in `docs/decisions/` | - |

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

### 2026-11-18 - Phase 1 step 6: API contract and documentation
**Branch:** `phase-1/docs` | **Phase:** 1 - Foundation
**Done:**
- OpenAPI contract with `dedoc/scramble`: `docs/api/openapi.json` (17 endpoints, bearer security, public endpoints marked open). Interactive "Try it" page at `/docs/api` (local and staging only, never production). Every endpoint has a group, title and description naming the permission it needs; every error response is documented with the real `{message, errors, code}` shape (`app/Support/Api/ErrorEnvelopeDocumentation.php`).
- `composer docs` regenerates the spec; a test fails `composer ci` if the committed file is stale or an API route is undocumented, so a contract change always shows up as a reviewable diff.
- Written docs: `docs/architecture.md`, `docs/erd.md` (Mermaid), `docs/api/README.md`, `docs/api/frontend-guide.md` (for the frontend team: sign-in flow, the two email-link pages, error codes, pagination, CORS, rate limits), `docs/decisions/0001` to `0007` (ADRs), and `docs/manual-testing.md` (guided walk through every feature in PowerShell).
- The manual testing guide was verified by running every step against a throwaway database (since deleted): all behaviour matched, including lockout, invitations, password reset, role rules, audit log, docs page and the CORS preflight.
- 140 tests pass.
**Decided:** the interactive docs are available on `local` and `staging` environments only. Contract changes are reviewed through the `openapi.json` diff.
**Needs owner:** work through `docs/manual-testing.md`; send the frontend guide and spec to the frontend team (D3); decide the production database engine (ADR 0006).
**Next:** step 7 - staging deploy script and `docs/deployment.md`, needs the server details (D2).

### 2026-11-20 - Slice 0 start: auth aligned with the contract
**Branch:** `phase-1/auth-contract` | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:**
- Read the new scope (`updated scope.md`, draft CLAUDE.md v0.3) and the backlog PDF, compared them with what exists, and agreed a revised plan with the owner: fix the auth surface first so it is tested once, then audit hardening, tenancy, academic model, results locking, fees, announcements.
- Commit `Add forced password change`: `users.must_change_password`, middleware `password.changed` (403 `password_change_required`; `/me`, change password and logout exempt), cleared by change or reset. Demo account `first-login@demo.test` (re-seeding resets it).
- Commit `Add academic_affairs and ict_admin roles`: `ict_admin` has the same account-management permissions as `head_of_academics`; `academic_affairs` has none yet.
- Commit `Align API routes and response shapes with the contract`: success bodies wrapped in `data` (login included, with roles and permissions); `/auth/password/forgot|reset|change`; `GET /health`; `FRONTEND_ORIGINS`; docs at `/docs` and `/docs.json`; `tests/Feature/ApiContractTest.php` pins it.
- Commit `Add Postman collection, ADRs and handover docs`: `docs/api/postman/` (27 requests, saved sign-ins per demo role), ADRs 0008 and 0009, `CHANGELOG.md`, `docs/handover-checklist.md`; updated the frontend guide, API README, manual testing guide, ERD, architecture and README.
- Checked end to end: replayed the whole collection against the running API (all 27 steps as expected, including the forced password change and role restrictions) and `composer ci` is green (Pint, Larastan level 6, 157 tests).
**Decided:** [0008](decisions/0008-success-envelope-and-route-names.md) success envelope and route names; [0009](decisions/0009-forced-password-change.md) forced password change; `ict_admin` permissions (owner); keep invitations and add forced change (owner); synthetic demo grade scale and no GPA (owner); demo through a tunnel (owner); server-to-server frontend access is only a recommendation until the frontend team confirms.
**Needs owner:** push or approve pushing `phase-1/auth-contract`, then merge; test with Postman; ask the frontend engineer: server-side or browser calls, demo target app and origins, screens for Monday, payload shapes (D3), whether `{data, meta}` suits them, who builds the `/accept-invitation` and `/reset-password` pages; decide whether `updated scope.md` replaces `CLAUDE.md`.
**Environment notes (facts):** XAMPP's database is MariaDB 10.4.32 (not MySQL 8). Its panel kept stopping the server; running `mysqld` from a console worked (cause not found, not verified). The local `lems_backend` database now holds the demo users and one invited test user (`new.lecturer@demo.test`) plus audit entries from the collection replay.
**Next:** `phase-1/audit-hardening` (triggers and `lems_app` user, approved by the owner), then `phase-1/tenancy`.

### 2026-11-20 - Testing round and frontend connection guide
**Branch:** `phase-1/auth-contract` | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:**
- Tested in three layers: `composer ci` (162 tests); a live script against a running API on a scratch database (`lems_backend_test`, 148 checks: routing, CORS, every demo role, invitations, passwords, lockout, forced change, audit contents); and the Postman collection run with Newman (folder 9: 35 requests, 68 assertions; folders 0 to 4 also run). All pass. The owner's real `lems_backend` database was not touched (checked: same row counts before and after).
- Bug found and fixed: login answered `expires_at: null` although tokens expire after 12 hours (commit `Report the real token expiry at login`, with a test).
- Gap found and closed: the API did not trust a tunnel or proxy's forwarded headers, so behind ngrok every caller would share one IP (rate limit, audit log) and links would say http. Added `TRUSTED_PROXIES` (off by default; `*` for a tunnel demo) in `app/Support/Http/ProxyTrust.php`, with tests (commit `Add TRUSTED_PROXIES for tunnels and proxies`).
- The Postman collection now has tests on every request and a one-click folder "9. Automated checks".
- New docs: `docs/api-testing-postman.md` (how to test with Postman, expected results, last verified) and `docs/frontend-integration.md` (how the frontend connects: recommended server-to-server, tunnel setup, settings, example calls, joint test script, troubleshooting). Handover checklist, README and CHANGELOG updated.
**Decided:** none new. Recommendation recorded in the guide: give the demo accounts stronger passwords (a `DEMO_PASSWORD` setting) before the tunnel is public; waiting for the owner's answer.
**Needs owner:** test with Postman (`docs/api-testing-postman.md`); decide on `DEMO_PASSWORD`; push and merge the branch; answers from the frontend engineer; review the database schema proposal given in chat (tenancy columns, academic model, results, fees, announcements) before those migrations are written.
**Environment notes (facts):** ngrok 3.39 is installed with a valid config (whether an authtoken is set is not verified). Node 24 is available. XAMPP database is MariaDB 10.4.32.
**Next:** `phase-1/audit-hardening` (triggers, `lems_app` user), then `phase-1/tenancy`.
