# 0004 - Append-only audit log written with the change

Date: 2026-11-18 | Status: Accepted | Decided by: Iddriss (requirement from the project brief)

## Context

Student records, results, fees and roles are sensitive. The college must be able to answer "who changed this, when, from where, and what was it before?". The brief requires an audit record for every change to results, fees and roles.

## Decision

- A custom `audit_logs` table (not a package) records: event name, actor, subject (short type plus public id), before and after values, IP address, user agent and time.
- **Append-only.** The model refuses update and delete, and the API only reads. In production the application's database user should also be denied `UPDATE` and `DELETE` on this table, because raw SQL bypasses the model.
- **Written with the change.** Actions dispatch a domain event inside the same database transaction as the change. A synchronous subscriber (`AuditSubscriber`) turns it into a row. If the audit write fails, the change is rolled back. A test proves this.
- **Only chosen facts are stored** (role names, a status, an email), never passwords, tokens or whole records.
- Reading it needs the `audit.view` permission, held by `super_admin` only for now.
- Entries are kept indefinitely.

## Alternatives considered

- **spatie/laravel-activitylog:** convenient, but logs model changes automatically, which makes it easy to capture sensitive fields by accident and harder to guarantee atomicity per business action.
- **Queued audit writes:** faster, but an entry could be lost if the queue fails after the change commits.

## Consequences

- A trustworthy history with a small, explicit code surface.
- Every new module must dispatch events and add cases to `AuditEvent`.
- Listeners that email or call other services must be queued and kept out of the audit subscriber.
- To do at deployment: restrict the production database user (see `docs/deployment.md` once written).
