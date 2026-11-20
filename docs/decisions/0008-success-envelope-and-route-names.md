# 0008 - Success envelope and route names follow the agreed contract

Date: 2026-11-20 | Status: Accepted | Decided by: Iddriss

## Context

The Slice 0 scope (`updated scope.md`, section 6) proposes one contract for the frontend team: success bodies as `{ data, meta }`, password routes under `/auth/password/...`, a `/health` endpoint, an origins setting called `FRONTEND_ORIGINS` and the docs at `/docs`. Until now our responses were mixed: login returned a flat object, `/me` and lists were wrapped in `data`, message-only answers had a bare `message`. Nothing had been sent to the frontend yet, so changing it cost nothing.

## Decision

- Every successful JSON answer is wrapped in `data`. Lists add `links` and `meta` (pagination). Anything that is not part of the record goes in `meta` (for example `meta.can_assign` on `GET /roles`). Message-only answers are `data: { message }`. No body is `204`.
- Login answers `data: { token, token_type, expires_at, user }`, where `user` carries `roles`, `permissions` and `must_change_password`.
- `GET /health` and `GET /ready` are the exception: they stay unwrapped because uptime monitors read them.
- Routes: `POST /auth/password/forgot`, `/auth/password/reset`, `/auth/password/change` (renamed from `forgot-password`, `reset-password`, `change-password`).
- CORS origins come from `FRONTEND_ORIGINS` (default `https://thescnmtc-lms.fly.dev`), never `*`.
- Interactive docs at `/docs`, spec at `/docs.json`, still only on local and staging.
- `tests/Feature/ApiContractTest.php` fails if any of this changes by accident.

## Alternatives considered

- **Keep the mixed shapes and document them:** the frontend would need special cases per endpoint.
- **Wrap `/health` too:** consistent, but the scope draft shows it flat and monitoring tools expect that.

## Consequences

- The frontend can unwrap `data` in one place.
- Once the frontend team depends on these shapes, changing them is a breaking change (see `docs/api/README.md`).
- The frontend team may ask for different shapes (D3); if so, a new record supersedes this one.
