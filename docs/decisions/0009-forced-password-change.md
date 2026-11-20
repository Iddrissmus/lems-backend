# 0009 - Forced password change for issued accounts

Date: 2026-11-20 | Status: Accepted | Decided by: Iddriss

## Context

Backlog items BE-006 and DM-6 require that accounts created for existing students and staff, with a password someone else chose, are forced to change it at first login. Our accounts so far were invitation-only: the person sets their own password by opening an emailed link, so no one else ever knows it.

## Decision

Keep invitations and add a `must_change_password` flag on `users`:

- It is `true` for accounts issued with a password chosen by someone else (seeded demo users now; bulk-created students later).
- It is `false` for invitation accounts: the person picks their own password.
- While it is `true`, every endpoint except `GET /me`, `POST /auth/password/change` and `POST /auth/logout` answers `403` with `code: "password_change_required"` (middleware `password.changed`).
- Changing the password, or resetting a forgotten one, clears it.
- The flag is returned at login and in `/me` so the frontend can show the change-password screen.
- `first-login@demo.test` is a demo account that has the flag set; the other demo accounts do not, so they can be used straight away. Re-running `DemoUsersSeeder` resets it.

## Alternatives considered

- **Replace invitations with admin-set temporary passwords:** weaker, because someone else knows the password until it is changed.
- **No enforcement, only a flag the frontend respects:** the frontend must never be the only filter.

## Consequences

- Bulk import (BE-013) and credential issue (BE-006) will set the flag for the accounts they create.
- Staff roles added later inherit the behaviour without changes.
