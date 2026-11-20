# Handover checklist

What a new maintainer needs, and whether it exists yet. Updated in the same branch as the work (see `docs/PROJECT_LOG.md`). The backlog item for this page is BE-099 (technical handover documentation and the administrator guide).

Status: **done**, **partial** (exists, grows with each module) or **todo**.

## Run, test, understand

| Document | Status | Notes |
|---|---|---|
| `README.md` | done | Local setup on Windows (PHP, XAMPP MariaDB/MySQL, `composer ci`) |
| `docs/architecture.md` | partial | Layers and request flow; add each new domain as it is built |
| `docs/erd.md` | partial | Database diagram; update with every migration. Part 2 is the proposed Slice 0 model |
| `docs/decisions/` (ADRs) | partial | Why things are the way they are; one record per decision (0001 to 0011) |
| `docs/demo-data.md` | done | What the synthetic demo data contains and how to reset it |
| `docs/frontend-portal-review.md` | partial | What the frontend screens expect; input to the API contract |
| `docs/PROJECT_LOG.md` | partial | Chronological log of what was done; read it first to pick up where we left off |
| `CHANGELOG.md` | partial | What changed, newest first |

## For the frontend team

| Document | Status | Notes |
|---|---|---|
| `docs/api/openapi.json` | partial | Generated from the code, checked by `composer ci` |
| `docs/api/frontend-guide.md` | partial | Sign-in flow, shapes, errors, roles, CORS (the scope calls this `frontend-integration.md`) |
| `docs/api/postman/` | partial | Collection and local environment with every demo role; every request has tests, folder 9 is a one-click check |
| `docs/frontend-integration.md` | partial | Connecting the frontend: tunnel, settings, example calls, joint test |
| `docs/api/README.md` | done | How the contract is produced and changed |

## Testing and demo

| Document | Status | Notes |
|---|---|---|
| `docs/api-testing-postman.md` | partial | Test the whole API with Postman; last verified date and results at the bottom |
| `docs/manual-testing.md` | partial | Guided walk through every feature in PowerShell, with a checklist |
| `docs/demo.md` | todo | 10-minute demo script (Slice 0) |

## Operations

| Document | Status | Notes |
|---|---|---|
| `docs/deployment.md` | todo | Server provisioning checklist, `deploy.sh`, restricted DB user for `audit_logs` |
| `docs/runbook.md` | todo | What to do when something breaks; restore from backup |
| `docs/data-protection.md` | todo | Act 843: lawful basis, retention, subject access, 24-hour breach procedure (BE-092) |
| Backup and recovery plan | todo | Daily off-site backups, 24-hour RPO, 4-hour RTO, monthly restore test (BE-093) |
| Administrator guide | todo | For college staff: creating accounts, roles, what the audit log shows |

## Things only the owner holds (not in the repo)

- Production `.env` values and the database passwords.
- Hosting, domain and TLS accounts (D2).
- Mail provider credentials; the payment and SMS gateway accounts the College opens in its own name (D6, D8).
- GitHub repository access.
