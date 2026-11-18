# LEMS SCNMTC Backend — Project Brief for Claude Code

> STATUS: Final — awaiting owner (Iddriss) approval. Items marked **[ASSUMPTION]** or listed under "Open decisions" must be confirmed before they are built.

## 1. What we are building

The backend API for **LEMS SCNMTC**, the college portal of **Savior Church Nursing & Midwifery Training College** (Osiem, Eastern Region, Ghana). The existing frontend (https://thescnmtc-lms.fly.dev) presents the portal as "Records, results and fees, in one place": students view results, check fees and read College notices; staff manage them.

- Backend is built **from scratch** in its own repository. The **frontend is a separate repository owned by someone else** (a Laravel app). We do not change it. The API must therefore be **contract-first**: the OpenAPI spec is agreed with the frontend team before endpoints are implemented.
- The owner (Iddriss) builds, deploys and documents the backend and wants **oversight of every decision**. See "Working agreement" (section 12).

## 2. Open decisions (do not silently pick; propose and wait)

| # | Decision | Default if owner says "your call" |
|---|---|---|
| D1 | How the frontend authenticates: Sanctum SPA cookies (needs same parent domain) vs bearer tokens | **[ASSUMPTION]** Bearer tokens (Sanctum personal access tokens) |
| D2 | Production server: provider, OS, SSH access, control panel (cPanel or none), is Docker allowed | **[ASSUMPTION]** Ubuntu VPS, Nginx + PHP-FPM + Supervisor, no Docker needed in production |
| D3 | Frontend screens/fields. The owner has **no access to any portal**, so they cannot be inspected | Design the API from this brief; ask the frontend team for their expected payload shapes (or a staging login); agree the OpenAPI contract with them before building each module |
| D4 | Existing data to migrate from any old system | None assumed |
| D5 | Grading scale, GPA/CWA rules, pass marks for nursing/midwifery | Must come from the college; do not invent |
| D6 | SMS provider for notices (Ghana) | Email first; SMS behind an interface |
| D7 | Where the new git repo is hosted (GitHub, GitLab, Gitea, own server) | Owner decides; nothing in this brief depends on a specific host |

## 3. Stack

- PHP 8.3+, Laravel 11/12 (API-only; no Blade UI)
- MySQL 8.x (InnoDB, utf8mb4, strict SQL mode on; ULIDs for public IDs, bigint internal keys acceptable)
- Redis (queues, cache, rate limiting)
- Laravel Sanctum (auth), `spatie/laravel-permission` (roles/permissions), `spatie/laravel-activitylog` or custom audit table
- Pest for tests, Larastan (level 6+), Laravel Pint for style
- Scribe or `dedoc/scramble` for generated OpenAPI docs
- Files on a private local disk behind Laravel's filesystem abstraction (swappable to S3-compatible storage later)
- Docker (Laravel Sail) is optional for local dev; production is a plain server install (see section 11)

Owner develops on Windows (HP laptop) migrating from Ubuntu: prefer Docker/WSL2 workflows, and avoid shell scripts that only work on Linux without a documented Windows path.

## 4. Architecture rules

- Versioned API under `/api/v1`. JSON only. Consistent envelope for errors (RFC 7807-style `{message, errors, code}`).
- **Thin controllers.** Controller → Form Request (validation) → Action/Service class (business logic) → Eloquent model → API Resource (output). No business logic in controllers or models' magic.
- **Authorization via Policies** on every endpoint; never trust the frontend to hide things.
- Organize by domain: `app/Domain/{Academics,Results,Fees,Communication,Admissions,Identity}/...` with Actions, DTOs, Enums, Events.
- Use PHP backed Enums for statuses. Use Events + queued Listeners for side effects (emails, notifications).
- DB access: eager-load to avoid N+1 (enable `Model::preventLazyLoading()` outside production). Add indexes for every foreign key and common filter.
- All list endpoints: pagination (cursor or page), filtering, sorting, with documented limits.
- Money: stored as **integer pesewas** (GHS minor unit) in a `*_pesewas` column, currency column `GHS`. Never floats.
- Dates in UTC in the DB; app timezone Africa/Accra for display logic.
- Wrap every money, result-publishing and role change in a DB transaction. Never rely on MySQL implicit type coercion; keep strict mode on in every environment.
- Soft deletes on business records; hard deletes only by explicit decision.
- Every state change to results, fees, or roles writes an **audit record** (who, what, before/after, IP, when).
- Idempotency keys on payment-recording endpoints.

## 5. Identity, roles and access

- **Accounts are invite-only / admin-created.** Public self-registration is disabled in the API (the frontend currently shows `/register`; coordinate with the frontend team, see D3). **[ASSUMPTION]** — confirm with the owner.
- Roles: `super_admin`, `head_of_academics`, `registrar`, `bursar`, `lecturer`, `student` (more later: `admissions_officer`, `ict_admin`).
- Endpoints: login, logout, current user (`/me`), forgot/reset password (emailed link, expiring token), change password, invitation accept.
- Login rate limiting, lockout after repeated failures, password policy, optional email verification.
- Students and staff are separate profile records linked to a `users` row.

## 6. Modules and phases

Build in phases; each phase ends with tests, docs and an owner review before the next starts.

### Phase 1 — Foundation
Project skeleton, Docker, CI, health check, error handling, auth, roles/permissions, audit log, seeders, OpenAPI docs scaffold, staging deploy.

### Phase 2 — Academic structure
Programs (Nursing, Midwifery, Student Development…), cohorts/intakes, academic years and semesters, courses/modules, student profiles (index number, program, cohort, status), staff profiles, course assignments to lecturers, course registration.

### Phase 3 — Results
Assessments per course (continuous assessment, exams), score entry by lecturers, approval flow (lecturer → head of academics), grade calculation per the college's scale (D5), semester results, cumulative standing, publish/lock, corrections with audit trail, student results view, transcript data export.

### Phase 4 — Fees
Fee structures per program/year, invoice generation per student per semester, **manual payment recording** by the bursar (amount, method, reference, date, receipt number), balances, statements, receipts (PDF), reversals via counter-entries (never edit history).
Design a `PaymentProvider` interface now (`initiate`, `verify`, `handleWebhook`) with a `ManualProvider` implementation, so Paystack/Hubtel/MoMo can be added later without schema changes.

### Phase 5 — Communication (requested by owner; included in first release)
Notices/announcements targeted by audience (all, role, program, cohort, individual), publish/schedule/expire, read receipts, notification delivery via email (queued), SMS behind an interface (D6), in-app notification list. Fee-reminder and results-published notifications triggered by events.

### Phase 6 — Admissions (later)
Public enquiry/application endpoints (rate limited, spam protected), document upload, review workflow, conversion of an accepted applicant to a student account.

## 7. Data model sketch (to be refined after D3/D5)

users, roles/permissions (spatie), student_profiles, staff_profiles, programs, cohorts, academic_years, semesters, courses, course_offerings, enrolments (course registration), assessments, assessment_scores, semester_results, grade_scales, fee_structures, fee_items, invoices, invoice_lines, payments, payment_allocations, receipts, notices, notice_audiences, notice_reads, notifications, audit_logs, invitations, files.

Provide an ERD (Mermaid) in `docs/erd.md` and keep it current with migrations.

## 8. API conventions

- Resource-oriented REST: `GET /api/v1/students/{id}/results`, `POST /api/v1/invoices/{id}/payments`.
- Always use API Resources; never return raw models.
- Validation errors: HTTP 422. Auth: 401/403. Not found: 404. Conflicts: 409.
- Publish the OpenAPI spec (`docs/openapi.yaml` or generated) and **share changes with the frontend team before merging breaking changes**. No breaking change inside `/v1` once the frontend depends on it.
- CORS locked to known frontend origins via env config.

## 9. Security and privacy

Student records, results and fees are personal data (Ghana Data Protection Act, 2012). Therefore:
- Least-privilege policies; students only ever see their own data.
- Secrets only in environment variables; `.env` never committed; `.env.example` maintained.
- HTTPS only, secure cookies/headers, rate limiting, request size limits, validated file uploads (type, size, private storage with signed URLs).
- No PII in logs. Mask emails/phones in error trackers.
- Encrypted backups; documented restore procedure tested at least once.

## 10. Testing and quality gates

- Pest feature tests for every endpoint (happy path, validation, authorization per role).
- Unit tests for grade calculation, balance calculation, money handling.
- Factories + seeders for realistic demo data.
- **No hosted CI for now.** A single `composer ci` script runs Pint, Larastan, Pest and a migration run on a clean test database. It must pass before any merge, enforced by a git pre-push hook. A CI runner can be added later if the repo host allows (D7).
- Minimum coverage target agreed with owner (suggest 80% on Domain code).

## 11. Deployment and operations

- Deployed to a **server the owner controls** (D2), with separate **staging** and **production** environments, each with its own MySQL database and `.env`.
- Server stack: Nginx, PHP-FPM, MySQL, Redis, Supervisor (queue workers), cron (Laravel scheduler), Let's Encrypt TLS, firewall, SSH key login only.
- Release flow: work on a branch → `composer ci` passes → merge → deploy to staging → owner approval → deploy to production. Deploys use a versioned `deploy.sh` (or Deployer) with release directories and a symlink swap, so rollback is instant. Steps: pull code, `composer install --no-dev -o`, `php artisan migrate --force`, cache config/routes, restart queue workers. Destructive migrations need owner approval.
- `docs/deployment.md` must contain a full server provisioning checklist so the server can be rebuilt from scratch.
- Health (`/up`) and readiness endpoints; error tracking (Sentry or similar); structured logs; uptime check (the live site returned a 502 during discovery, so monitoring matters).
- Daily automated DB backups with retention policy.

### Documentation (owner is responsible; Claude drafts)
- `README.md` (setup on Windows/WSL2 and Linux), `docs/architecture.md`, `docs/erd.md`, `docs/api` (OpenAPI), `docs/deployment.md`, `docs/runbook.md` (incidents, backups, rollbacks), `docs/decisions/` (ADRs), `CHANGELOG.md`.

## 12. Working agreement (how Claude Code must work with the owner)

The owner wants a hand in everything. Therefore:
1. **Plan before code.** For each module or non-trivial change, first post a short plan (files, migrations, endpoints) and wait for approval.
2. **Work from a new dedicated repository** for the backend (separate from the frontend repo). Small, reviewable commits and branches, one concern each, with clear messages. Never commit directly to `main`.
3. **Never** run destructive commands (dropping tables, `migrate:fresh`, deleting files) against anything but local/test data without explicit approval.
4. **Do not invent business rules** (grading scale, fee rules, pass marks). Ask, record the answer in `docs/decisions/`, then implement.
5. Record each significant decision as an ADR.
6. When something is unclear or the frontend contract is unknown, stop and ask instead of guessing.
7. Keep `.env.example`, docs and OpenAPI in sync with code in the same change.
8. Explain choices briefly so the owner can follow and learn.

## 13. Definition of done (per feature)

Migration + model + policy + request + action + resource + route; tests passing; OpenAPI updated; audit logging where applicable; seeders updated; owner has reviewed.

## 14. Immediate next steps

1. Owner approves this brief (and settles D1–D6 where possible).
2. Ask the frontend team for the payload shapes they expect, or agree the contract with them (D3).
3. Create the backend repository (D7) and confirm the server details (D2).
4. Phase 1: scaffold Laravel project, `composer ci` script and git hook, auth, roles, audit log, staging deploy script.
