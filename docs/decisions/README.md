# Decision records (ADRs)

One short file per significant decision, so anyone can learn why the system is the way it is. A decision is never deleted; if it changes, a new record replaces it and the old one is marked "Superseded".

| # | Decision | Status |
|---|---|---|
| [0001](0001-laravel-13-api-only.md) | Laravel 13, API only | Accepted |
| [0002](0002-bearer-token-authentication.md) | Bearer tokens for frontend authentication (D1) | Provisional |
| [0003](0003-error-envelope.md) | One error format for every endpoint | Accepted |
| [0004](0004-audit-log.md) | Append-only audit log written with the change | Accepted |
| [0005](0005-roles-and-permissions.md) | Roles hold permissions; who manages accounts | Accepted |
| [0006](0006-local-environment-and-database.md) | Local environment and database engine | Accepted for local, open for production |
| [0007](0007-invite-only-accounts-and-lockout.md) | Invite-only accounts, password policy and login lockout | Accepted, policy to be confirmed |

## Template

```markdown
# NNNN - Title
Date: YYYY-MM-DD | Status: Accepted | Decided by: Iddriss
## Context
## Decision
## Alternatives considered
## Consequences
```
