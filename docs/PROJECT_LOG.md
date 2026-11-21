# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 1 - Foundation, aimed at the **Slice 0 demo on Monday 23 Nov 2026**. Demo flow (from the frontend engineer): student registration, lecturer mark entry and submit, Head of Academics release, student results; staff and admin flows are extras; fees and announcements are dropped unless time is left
- Branches: `main` (on GitHub) holds everything up to the registration endpoints (PR #5 merged). `phase-1/marks` (marks, release and results) is **pushed, awaiting your pull request**. `phase-1/demo-setup` (branched from it: `DEMO_PASSWORD` and `docs/demo.md`) is local, **not pushed yet**
- Done so far: steps 1 to 6; the whole demo flow is built and tested (registration; lecturer marks with the lock; Head release; student results; grade scale); `DEMO_PASSWORD` is in place and applied to the local database; `docs/demo.md` is written. 458 tests pass; verified over real HTTP (folder 9 with 60 steps, folder 13 with 29 steps, using the configured demo password)
- Next: the joint test through the tunnel with the frontend engineer (needs the tunnel open: `docs/demo.md` sections 3 and 4), his answers to the contract, then the audit-log triggers and the `lems_app` MySQL user, and the deployment, runbook and data-protection docs
- Waiting on owner: pull request for `phase-1/marks`, then push approval for `phase-1/demo-setup`; permission to open the tunnel and change `.env` for it (`APP_DEBUG=false`, `TRUSTED_PROXIES=*`); the frontend engineer's files and answers to Q1 to Q13 in `docs/api/demo-contract.md`; production database engine (XAMPP is MariaDB 10.4.32, see ADR 0006)

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

### 2026-11-20 - Frontend findings, multi-tenancy and the proposed data model
**Branch:** `phase-1/tenancy` (stacked on `phase-1/auth-contract`) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:**
- With the owner's approval and confirmation that the data is synthetic, took a read-only look at the frontend student portal (GET pages only; no credentials or personal data saved). Findings are in memory and summarised in `docs/frontend-integration.md` terms: it is a Livewire app with its own login (Fortify), student screens (dashboard, announcements, course registration, results, transcript, profile), it already states that only the Head of Academics releases results, and shows a sample 4.0 GPA labelled not official.
- Owner decisions recorded: registration covers both staff creating the student and the student registering for courses; release by `head_of_academics`; mark entry on screen; students see letter grades and credit hours only (the frontend shows its own sample GPA and hides the weighted average %); wait for the frontend engineer's files before writing the API contract.
- Multi-tenancy (BE-001), built in and switched off, commit `Add multi-tenancy, switched off`: `tenants` table with a default tenant created by a migration, `tenant_id` (NOT NULL, no default) on `users` and `audit_logs`, `BelongsToTenant` trait and `TenantScope` (fail closed), `TenantContext`, `tenant` middleware, `PersonalAccessToken` model that loads the token owner without the scope, `TENANCY_ENABLED=false`. 16 cross-tenant tests (models and API): tenant B cannot see, change or disable tenant A's users, sees only its own audit trail, a cross-tenant id answers 404.
- Bug found by running the seeder: `DatabaseSeeder` used `WithoutModelEvents`, which disables the hook that stamps `tenant_id`; the NOT NULL column made it fail loudly. Removed the trait and added a test.
- Verified: migration rolls back and re-applies; applied to the real local database (10 users and 13 audit entries all backfilled to the default tenant); `composer ci` green (179 tests); the live script (148 checks) and the Postman folder (35 requests, 68 assertions) pass over real HTTP with the tenancy code in place.
- Wrote the proposed Slice 0 data model: `docs/erd.md` Part 2 (17 new tables, state diagram of marks and release, seven open points), [0010](decisions/0010-multi-tenancy-built-in-switched-off.md) (accepted) and [0011](decisions/0011-slice-0-data-model.md) (proposed), architecture section on tenancy.
**Decided:** ADR 0010 (accepted): email stays globally unique for now; roles, invitations and tokens are not tenant-scoped; tenancy must not be switched on until host-based tenant resolution exists. ADR 0011 is **proposed**, not decided.
**Needs owner:** review ADR 0011 and the ERD; push and merge the two branches; the frontend engineer's files and answers; open points 1 to 7 in `docs/erd.md` (mark structure, grade scale, registration rules, student statuses, index number format, email uniqueness, field names).
**Next:** when his files arrive, write the API contract; then the academic model migrations on a new branch.

### 2026-11-20 - Admin dashboard review and the academic foundations
**Branch:** `phase-1/academic-foundations` (stacked on `phase-1/tenancy`) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:**
- With the owner's confirmation that the account and data are demo-only and synthetic, took a read-only look at the frontend admin dashboard (GET pages only; no credentials or personal data saved). Findings in `docs/frontend-portal-review.md`: registration windows, add-an-account that returns the created password and index number once, admin password resets, a roles and permissions matrix with colon-style names, mark entry by course, student status requests, CSV import, graduation list, and HR screens that have no backlog item (not built).
- Built the foundations (owner approved "Foundations now"): migrations for 18 tables in four groups (`2026_11_20_1300xx`), generated models with `BelongsToTenant` and public ULIDs, 18 factories, enums, the `GradeLocked` exception (423), `IssueIndexNumber` (row-locked sequence, placeholder format), `audit_logs.reason`.
- Grade lock enforced in the database: triggers reject any UPDATE or DELETE of a submitted score; tested with raw SQL, bulk updates and model events switched off, plus the model-level 423.
- `registration_windows` and `course_offerings.exam_date` added to the design because of the admin screens; ERD Part 2 and ADR 0011 updated (ADR 0011 now accepted for the foundations).
- Synthetic demo data (`AcademicDemoSeeder`, local only, re-runnable): 3 departments, 2 programmes, 1 academic year with two 16-week semesters, 6 cohorts, 12 courses, 9 offerings each with a lecturer and an "Overall" assessment, 8 lecturers, 41 students, about 66 registrations, an open registration window, a synthetic grade scale. The demo student has nothing registered, so registering can be shown live. `docs/demo-data.md`.
- Tests: 62 new (tenant isolation for every model plus a guard that every table with a `tenant_id` has a scoped model, all unique rules, index numbers, the lock, the seeder); 234 pass. Migrations verified to apply, roll back (removing the triggers) and re-apply, and applied to the owner's local database.
- Two MariaDB 10.4 pitfalls found and fixed: NOT NULL `timestamp` columns get invalid defaults and silent auto-updates (use `dateTime`), and index names over 64 characters fail.
**Decided:** none new beyond ADR 0011's update. Pending decision: adopt the frontend's permission names (recommended) and add a release permission.
**Needs owner:** approve pushing the branch; the frontend engineer's answers (see the questions in `docs/frontend-portal-review.md`); `DEMO_PASSWORD`.
**Next:** the API contract draft for the demo flow, then the registration endpoints.

### 2026-11-20 - Draft API contract for the demo flow
**Branch:** `phase-1/academic-foundations` | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:** pushed the foundations branch (pre-push `composer ci` green). Wrote `docs/api/demo-contract.md`, draft v0: ground rules already built, a proposed permission set (the frontend's colon-style names plus `results:release`, `registration:self`, `results:view-own`), and endpoints with request and response JSON for reference data, registration windows, staff creating a student (temporary password returned once), student course registration (all or nothing, rejection reasons), lecturer mark entry and review and submit (423 `grade_locked`, `marks_incomplete`), Head of Academics dashboard and release, student results (letter grade and credit hours only, no GPA or percentages), an `Idempotency-Key` rule, new error codes, audit events, a table mapping his field names to ours, 10 assumptions (A1 to A10) and 13 questions (Q1 to Q13). Nothing in it is implemented.
**Decided:** none; every business rule in the draft is labelled as an assumption for him and the owner to confirm. Nothing invents a College rule (no credit limits, clashes, arrears gating, GPA).
**Needs owner:** send the contract to the frontend engineer; get his files (`docs/frontend/contracts`, `app/Contracts`, `app/Data`), answers to Q1 to Q13, and screenshots or fields of the lecturer mark-entry and Head release screens; decide `DEMO_PASSWORD`; confirm A5 to A9 with the College where they are College rules.
**Next:** freeze the contract after his reply, then build registration endpoints first.

### 2026-11-20 - Registration endpoints (contract sections 3 to 6)
**Branch:** `phase-1/registration` (from updated `main`) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done (owner said go):** five commits, each with tests and a green `composer ci`:
- Reference data and permissions: `GET /departments`, `/programmes`, `/cohorts`, `/semesters`, `/course-offerings` (filters programme, department, year, q, semester; paged). Permissions `accounts:create`, `accounts:reset-password`, `students:view`, `registration:manage`, `registration:self`, given to roles as proposed in the contract. `ApiValidationException` lets a 422 carry its own code with field errors.
- `Idempotency-Key` on every write (`idempotency_keys` table, tenant-scoped): same key and request returns the saved answer with `Idempotent-Replayed: true`; same key, different request is 422; a request still running is 409; only 2xx answers are saved so a failed request can be fixed and retried; saved answers are encrypted, because some carry a one-time password; pruned daily.
- Registration windows: list, open, close; status computed from the clock (not_yet_open, open, closed); audience of programme and year pairs; times stored in UTC.
- Staff register a student (`POST /students`): account, student record and index number in one transaction, optional first courses (no window needed, any refusal rolls everything back), generated temporary password returned once in `meta` and never stored readably or audited; `GET /students` with search and filters; `POST /users/{id}/reset-password` for active accounts.
- A student's own registration: `GET /me/student`, `/me/registration`, `/me/course-offerings`, `POST /me/registrations` (all or nothing, reasons per course), `DELETE /me/registrations/{id}` (withdraw, record kept). Only the student's own record is ever reachable.
- Audit events: `registration_window.created`, `registration_window.closed`, `student.registered`, `enrolment.created`, `enrolment.withdrawn`, `user.password_reset_by_admin`.
**Verified:** 398 tests (Pint, Larastan level 6); a 60-step Postman folder (122 assertions) passes against a running API, twice in a row; the manual folders 0 to 8 pass with the matching role; the original 148-check live script passes; the real local database was migrated and the role permissions re-seeded.
**Decided (assumptions from the contract, still for the frontend engineer and owner to confirm):** registration is all or nothing (A3); only window, offered-this-semester, curriculum and not-already-registered are checked, no credit limits, clashes or arrears (A4); a student needs a cohort (given, or found from a year that matches exactly one cohort); withdrawing needs an open window and no marks yet; the colon permission style for new permissions; `user.password_reset_by_admin` as the audit event name (it applies to any account).
**Needs owner:** push approval; the frontend engineer's answers (Q1 to Q13); `DEMO_PASSWORD`.
**Next:** marks (lecturer), then release and student results.

### 2026-11-21 - Marks, release and results (contract sections 7 to 9)
**Branch:** `phase-1/marks` (from `phase-1/registration`, which was pushed first) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done (owner said go):**
- Contract and ADR first: [0012](decisions/0012-demo-results-and-sign-in-decisions.md) records the four decisions the owner made after the frontend engineer's reply; `docs/api/demo-contract.md` sections 7 to 9 now carry "As built" notes and `GET /grade-scale`.
- Lecturer: `GET /lecturer/course-offerings`, `GET` and `PUT /lecturer/course-offerings/{id}/marks`, `POST .../marks/submit`. Only assigned courses (404 otherwise). Drafts are all-or-nothing per request; submit needs a mark for every registered student (422 `marks_incomplete`, naming who is missing), stores the letter grades with the scale used and locks the marks; later writes answer 423 `grade_locked`, and a write that reaches the database trigger is answered the same way.
- Head of Academics: `GET /academics/course-offerings` (status counts in `meta.summary`), `GET .../results` (scores, only once submitted), `POST .../release` (per course, repeatable, cannot be undone).
- Student: `GET /me/results` (released courses only; letter grade and credit hours; never a mark, percentage or GPA). Any signed-in user: `GET /grade-scale` (synthetic, `official: false`, sample points).
- Permissions `courses:teach`, `marks:enter`, `marks:submit`, `academics:dashboard:view`, `results:release`, `results:view-own`; audit events `marks.submitted` and `results.released` (facts only); error codes `marks_incomplete`, `already_submitted`, `not_submitted`, `no_grade_scale`.
- Postman: folders 10 to 12 (by hand) and folder 13, a 29-step scripted run of the whole flow; `docs/api-testing-postman.md` explains how to reset the demo data to run it again.
- Tests run with 512M of PHP memory (the suite outgrew 128M).
**Verified:** 455 tests (Pint, Larastan level 6); on a freshly seeded scratch database, folder 9 (60 requests, 122 assertions) and folder 13 (29 requests, 68 assertions) pass over real HTTP, and folders 10 to 12 pass by role. The real local database was migrated, roles and permissions re-seeded, and the demo seeder re-run (sample grade points added). The 148-check live script was not extended to marks.
**Decided:** [ADR 0012](decisions/0012-demo-results-and-sign-in-decisions.md): password-only sign-in through our API for Monday (passkeys and 2FA after the demo), one Overall mark out of 100, colon-style permission names, the frontend calculates a GPA labelled "sample, not official" from `GET /grade-scale`. Built on my own judgement (in the contract as "As built", for the frontend to confirm): release is per course; the Head cannot read drafts; academic_affairs can read the dashboard but not release; withdrawn students are left out of the class list and results.
**Needs owner:** pull request for `phase-1/registration`, then push approval for `phase-1/marks`; `DEMO_PASSWORD` decision; the frontend engineer's answers (Q1 to Q13) and files.
**Next:** send the frontend engineer the contract and the first-person message; start the tunnel and do the joint sign-in and registration test; write `docs/demo.md`; then the audit-log triggers and the `lems_app` user.

### 2026-11-21 - Demo password and the demo guide
**Branch:** `phase-1/demo-setup` (from `phase-1/marks`) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done (owner said go and gave the demo password):**
- `DEMO_PASSWORD` setting (`config/demo.php`): both demo seeders now use it instead of the literal `password`, and re-running them applies the current value to accounts that already exist (and puts `first-login@demo.test` back to "must change"). A value shorter than 8 characters is refused. The default stays `password` so a fresh clone still works. The real value is in the owner's `.env` only (git ignores it); it appears in no tracked file, no Postman file and no doc.
- Applied to the real local database: `.env` set, the academic demo seeder re-run (non-destructive), and the demo accounts checked to match the setting.
- Postman: logins use the environment variable `demo_password` (default `password` in the environment file; set the real value locally).
- `docs/demo.md`: checklist, `.env` settings for a tunnel, start-of-day order, the ten-minute script (one student through the whole flow, with what to say), a troubleshooting table, and what to do after. Other docs that said "the password is `password`" now point at the setting; the account table in the Postman guide was brought up to date.
**Verified:** 458 tests (Pint, Larastan level 6); on a scratch database seeded with the real setting, sign-in with the old default `password` is refused (66 assertions fail) and with the configured value folder 13 passes (29 requests, 68 assertions) and folder 9 passes (60 requests, 122 assertions).
**Decided:** none new. Owner's decision: demo password chosen by the owner. Risk noted for the owner: a short, all-number password on accounts that include `super_admin` is weak if the tunnel address is shared widely; the data is synthetic, sign-in is rate limited and locks per email, and the guide says to keep the address private and stop the tunnel after the demo.
**Needs owner:** pull request for `phase-1/marks`, then push approval for this branch; permission to start the tunnel and set the demo `.env` values; the frontend engineer's answers.
**Next:** open the tunnel when the owner says so, run the joint sign-in and registration test with the frontend engineer, then the whole flow; fix what it shows.

### 2026-11-21 - Tunnel test and sweep
**Branch:** `phase-1/demo-setup` | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done:** the owner started `php artisan serve` and ngrok. I set the demo `.env` values (`APP_DEBUG=false`, `APP_URL` to the tunnel address, `TRUSTED_PROXIES=*`, `FRONTEND_URL`), saved a copy of the previous `.env` outside the repo, and tested from outside: health and ready, sign-in, `/me`, sign-out, error bodies without internals, the live API docs page. The client IP in the audit log is the real public IP, so the proxy settings work. Added `scripts/demo-sweep.py` (read only: every role signs in and opens its screens; 23 checks) and a step for it in `docs/demo.md`.
**Verified:** on a scratch database seeded with the configured demo password, folder 9 (60 requests, 122 assertions) and folder 13 (29 requests, 68 assertions) pass. Through the public tunnel on the real demo data, the sweep passes 23 of 23, and the old default password is refused. No state-changing call was made against the presenting database.
**Finding:** the tunnel adds about 1 second to every call (connect 0.2 s, TLS 0.45 s, the rest travelling to the laptop and back); local calls take about 0.1 s. A portal page that makes several calls will feel slow; recorded in `docs/demo.md`. Not an API fault.
**Needs owner:** the frontend engineer's reply (nothing from him yet); merge the `phase-1/demo-setup` pull request; push approval for the commit added after it; keep ngrok, `artisan serve`, MySQL and the laptop awake until the demo.
**Next:** when he is online, a joint sign-in test and then the whole flow; rehearse once from `docs/demo.md` and reset with `php artisan migrate:fresh --seed`; restore the old `.env` values after the demo.

### 2026-11-21 - Tunnel rehearsal, reset and handover drafts
**Branch:** `phase-1/handover-docs` (from `main`, which now includes `phase-1/demo-setup`, PR #7) | **Phase:** 1 - Foundation (Slice 0 demo due 2026-11-23)
**Done (owner said go):**
- Rehearsal through the public tunnel on the real demo data: the whole 29-step flow (lecturer saves and submits, the lock refuses changes, the Head reviews and releases, the student sees a letter grade) passed, 68 assertions, 0 failures, about 40 seconds (about 1.3 s per call through ngrok).
- Then reset the local database with `php artisan migrate:fresh --seed` (checked first: only demo accounts existed) and confirmed over the tunnel that it is clean: no marks submitted or released, 41 students, 9 courses not started, window open. The sweep leaves a few sign-in audit rows; one more reset before presenting clears them.
- Wrote the three operations drafts that `docs/handover-checklist.md` listed as todo: `docs/deployment.md`, `docs/runbook.md`, `docs/data-protection.md`. Checked their factual statements against the code (readiness returns 503 when unhealthy, disabling and resetting revoke tokens, the last super admin is protected, `route:cache` and `event:cache` work, `/docs` is refused in production, `composer audit` finds nothing). Checklist updated.
**Decided:** none. Open and recorded in the documents: hosting and database engine (D2, ADR 0006), the data-protection decisions in `docs/data-protection.md` section 6 (lawful basis, retention, requests, breach procedure, storage location), sizing against the 4-hour recovery time.
**Needs owner:** the frontend engineer's reply (nothing yet); review the three drafts (they are drafts, not legal advice); keep the tunnel and laptop up until the demo.
**Next:** when he is online, a joint sign-in test and then the whole flow; decide by Sunday midday whether to build the fallback demo page; one last `migrate:fresh --seed` before presenting; restore the old `.env` values after the demo.
