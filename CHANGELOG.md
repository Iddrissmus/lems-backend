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
