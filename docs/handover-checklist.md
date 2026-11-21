# Handover checklist

What a new maintainer needs, and whether it exists yet. Updated in the same branch as the work (see `docs/PROJECT_LOG.md`). The backlog item for this page is BE-099 (technical handover documentation and the administrator guide).

Status: **done**, **partial** (exists, grows with each module) or **todo**.

## Run, test, understand

| Document | Status | Notes |
|---|---|---|
| `README.md` | done | Local setup on Windows (PHP, XAMPP MariaDB/MySQL, `composer ci`) |
| `docs/architecture.md` | partial | Layers and request flow; add each new domain as it is built |
| `docs/erd.md` | partial | Database diagram; update with every migration. Part 2 is the proposed Slice 0 model |
| `docs/decisions/` (ADRs) | partial | Why things are the way they are; one record per decision (0001 to 0012) |
| `docs/demo-data.md` | done | What the synthetic demo data contains and how to reset it |
| `docs/frontend-portal-review.md` | partial | What the frontend screens expect; input to the API contract |
| `docs/PROJECT_LOG.md` | partial | Chronological log of what was done; read it first to pick up where we left off |
| `CHANGELOG.md` | partial | What changed, newest first |

## For the frontend team

| Document | Status | Notes |
|---|---|---|
| `docs/api/openapi.json` | partial | Generated from the code, checked by `composer ci` |
| `docs/api/demo-contract.md` | partial | Draft v0 of the contract for registration, marks, release and results; replaced by the live OpenAPI once built |
| `docs/api/frontend-guide.md` | partial | Sign-in flow, shapes, errors, roles, CORS (the scope calls this `frontend-integration.md`) |
| `docs/api/postman/` | partial | Collection and local environment with every demo role; every request has tests, folder 9 is a one-click check |
| `docs/frontend-integration.md` | partial | Connecting the frontend: tunnel, settings, example calls, joint test |
| `docs/api/README.md` | done | How the contract is produced and changed |

## Testing and demo

| Document | Status | Notes |
|---|---|---|
| `docs/api-testing-postman.md` | partial | Test the whole API with Postman; last verified date and results at the bottom |
| `docs/manual-testing.md` | partial | Guided walk through every feature in PowerShell, with a checklist |
| `docs/demo.md` | done | Demo day guide: checklist, tunnel settings, the 10-minute script, troubleshooting |
| `scripts/demo-sweep.py` | done | Read-only check that every demo role signs in and opens its screens, locally or through the tunnel |

## Operations

| Document | Status | Notes |
|---|---|---|
| `docs/deployment.md` | partial | Draft: provisioning checklist, settings, releasing and rolling back by hand. Still to do: hosting and database engine (D2, ADR 0006), `deploy.sh`, restricted DB user for `audit_logs` |
| `docs/runbook.md` | partial | Draft: incidents by symptom, account and marks problems, security incident steps, routine jobs, manual backup and restore, rollback. Fill in the contacts; automate backups once hosted |
| `docs/data-protection.md` | partial | Draft: what data is held, who sees it, what protects it, the decisions the College must make (lawful basis, retention, requests, breach procedure). Needs the College and a qualified person (BE-092) |
| Backup and recovery plan | partial | The manual procedure and the requirements are in `docs/runbook.md` section 7; the automated off-site schedule depends on hosting (BE-093) |
| Administrator guide | todo | For college staff: creating accounts, roles, what the audit log shows |

## Things only the owner holds (not in the repo)

- Production `.env` values and the database passwords.
- Hosting, domain and TLS accounts (D2).
- Mail provider credentials; the payment and SMS gateway accounts the College opens in its own name (D6, D8).
- GitHub repository access.
