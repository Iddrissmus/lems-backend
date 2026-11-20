# Changelog

All notable changes to the LEMS SCNMTC backend. Newest first. The API version is in the OpenAPI document (`docs/api/openapi.json`); breaking changes inside `/api/v1` are not allowed once the frontend depends on them.

## Unreleased

### Added
- `must_change_password` flag: accounts issued with someone else's password must choose their own before using the API (`403 password_change_required`). ADR 0009.
- Roles `academic_affairs` and `ict_admin`.
- `GET /api/v1/health` (liveness for monitors).
- Postman collection and local environment in `docs/api/postman/`.
- `first-login@demo.test` demo account.

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
