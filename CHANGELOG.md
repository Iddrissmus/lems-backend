# Changelog

All notable changes to the LEMS SCNMTC backend. Newest first. The API version is in the OpenAPI document (`docs/api/openapi.json`); breaking changes inside `/api/v1` are not allowed once the frontend depends on them.

## Unreleased

### Added
- `must_change_password` flag: accounts issued with someone else's password must choose their own before using the API (`403 password_change_required`). ADR 0009.
- Roles `academic_affairs` and `ict_admin`.
- `GET /api/v1/health` (liveness for monitors).
- Postman collection and local environment in `docs/api/postman/`.
- `first-login@demo.test` demo account.
- `TRUSTED_PROXIES` setting so the API sees the real client IP and https behind a tunnel or reverse proxy (off by default).
- Postman tests on every request, and a one-click folder "9. Automated checks" (35 steps).
- `docs/api-testing-postman.md` and `docs/frontend-integration.md`.
- Multi-tenancy, built in and switched off (`TENANCY_ENABLED=false`): `tenants` table with a default tenant, `tenant_id` on `users` and `audit_logs`, `BelongsToTenant` scope, `TenantContext`, cross-tenant tests. ADR 0010. No change for users.
- Slice 0 data model, built: 18 tables (departments, programmes, academic years, semesters, cohorts, courses and curriculum links, course offerings, staff and student profiles, index number sequences, lecturer assignments, registration windows, enrolments, grade scales and bands, assessments, assessment scores), their models and factories, and `audit_logs.reason`. ADR 0011.
- Database-level grade lock: triggers reject any UPDATE or DELETE of a submitted score; the model answers `423 grade_locked`.
- Collision-safe index number issuing (`IssueIndexNumber`, placeholder format until the College confirms it).
- Synthetic demo data (`AcademicDemoSeeder`, local only): about 40 students, 8 lecturers, 12 courses, 2 semesters, an open registration window and a synthetic grade scale. See `docs/demo-data.md`.
- `docs/frontend-portal-review.md`: what the frontend's student and admin screens expect.
- `docs/api/demo-contract.md`: draft v0 API contract for the demo flow (nothing implemented yet), with assumptions and questions for the frontend engineer.

- Registration endpoints (docs/api/demo-contract.md sections 3 to 6): reference data (departments, programmes, cohorts, semesters, courses on offer), registration windows (list, open, close, with a computed status), staff registering a student (account, record and index number in one call; temporary password shown once; optional first courses), admin-issued temporary passwords, and a student's own registration (record, status, eligible courses, register all-or-nothing, withdraw).
- `Idempotency-Key` handling on every write: a retry returns the saved answer (stored encrypted) and does nothing twice; reuse with a different request is 422; a request still running is 409.
- Permissions `accounts:create`, `accounts:reset-password`, `students:view`, `registration:manage`, `registration:self`, held as proposed in the contract (colon style, to match the frontend).
- Audit events `registration_window.created`, `registration_window.closed`, `student.registered`, `enrolment.created`, `enrolment.withdrawn`, `user.password_reset_by_admin`. Passwords are never recorded.
- Error codes `registration_rejected`, `registration_closed`, `idempotency_key_reused`, `request_in_progress`; the error format can now carry field errors together with its own code.
- Postman: four new folders and 25 more steps in the one-click folder (now 60).
- Marks, release and results (docs/api/demo-contract.md sections 7 to 9): a lecturer's courses, class list with letter grade preview, draft marks, and submit (the marks lock, letter grades are stored with the scale used); the Head of Academics' dashboard by course, review of submitted marks and per-course release; a student's own released results (letter grade and credit hours only); `GET /grade-scale`.
- Permissions `courses:teach`, `marks:enter`, `marks:submit`, `academics:dashboard:view`, `results:release`, `results:view-own`, given to roles as proposed in the contract.
- Audit events `marks.submitted` and `results.released` (facts only, never the marks).
- Error codes `marks_incomplete`, `already_submitted`, `not_submitted`, `no_grade_scale`; a write that reaches the database lock trigger is answered `423 grade_locked`.
- Postman: folders 10 to 12 (by hand) and folder 13, a 29-step scripted run of the lecturer, Head and student flow.
- ADR 0012: sign-in, one Overall mark, colon-style permissions and the sample GPA from the frontend.
- `DEMO_PASSWORD` setting (`config/demo.php`, `.env`): the password of every synthetic demo account; the demo seeders apply it, including to accounts that already exist. Default `password`. The real value lives only in `.env`, which git ignores.
- `docs/demo.md`: the Monday demo guide (checklist for the days before, the settings for a tunnel, the ten-minute script, what to say, what to do when something goes wrong, after the demo).
- Postman: logins use the environment variable `demo_password`.
- `scripts/demo-sweep.py`: a read-only check that every demo role can sign in and open its screens, locally or through the tunnel.
- Handover drafts: `docs/deployment.md` (server provisioning, settings, releasing and rolling back), `docs/runbook.md` (what to do when something breaks, routine jobs, manual backup and restore) and `docs/data-protection.md` (what personal data is held, who sees it, the decisions the College must make, a breach procedure to confirm).

### Changed
- The demo grade scale now carries sample points (4, 3, 2, 1, 0) so a sample GPA can be shown; the API still never computes one.
- Test runs use up to 512M of PHP memory (the suite outgrew the 128M default).

### Fixed
- Login reported `expires_at: null` although tokens expire after 12 hours (Sanctum enforced it silently). The expiry is now stored on the token and returned.

### Changed (affects the frontend; nothing was sent to them before this)
- Every success response is wrapped in `data` (login included); `GET /roles` returns `meta.can_assign`. ADR 0008.
- Password routes renamed: `POST /auth/password/forgot`, `/auth/password/reset`, `/auth/password/change`.
- Login returns the user with `roles`, `permissions` and `must_change_password`.
- CORS origins setting renamed `FRONTEND_ORIGINS` (default `https://thescnmtc-lms.fly.dev`).
- Interactive docs moved to `/docs` (spec at `/docs.json`).

## 2026-11-18 - Phase 1, steps 1 to 6

- Project skeleton, quality gate (`composer ci`), error envelope and readiness check.
- Identity: login, bearer tokens, password reset, invitations, lockout.
- Roles, permissions and user management.
- CORS and the append-only audit log.
- OpenAPI contract, architecture, ERD, ADRs, frontend guide and manual testing guide.

See `docs/PROJECT_LOG.md` for the detailed log.
