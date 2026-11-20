# 0010 - Multi-tenancy is built in and switched off

Date: 2026-11-20 | Status: Accepted | Decided by: Iddriss (from the backlog, item BE-001)

## Context

The contract does not ask for multi-tenancy, and the College is not paying for it. It is in the backlog because the supplier plans to resell the platform to other colleges. Retrofitting tenancy onto a live system that holds real student data is exactly how data leaks between customers, and under the contract unauthorised disclosure is uncapped liability. Adding the column and the scope in week 1 costs days; adding them in year 2 means touching every query. So: build it now, leave it off.

## Decision

- A `tenants` table. A migration creates the **default tenant** (`scnmtc`), so every environment and every test database has it.
- A non-null `tenant_id` (foreign key) on every domain table. Today that is `users` and `audit_logs`; every table added from now on must have it and use the `BelongsToTenant` trait. Existing rows were assigned to the default tenant by the migration. The column has **no database default**: the application must always say whose row it is, and an insert that forgets fails loudly instead of landing in the wrong college.
- `BelongsToTenant` (a model trait) adds a global query scope, sets `tenant_id` on create, refuses to create a row for a different tenant than the current one, and refuses to move a row to another tenant. `tenant_id` is not mass-assignable.
- `TenantContext` answers "which college is this request for?". Tenancy off: always the default tenant. Tenancy on: the tenant set by the `tenant` middleware from the signed-in user. If it is unknown the scope **matches nothing** (fails closed) and creating a row throws `TenantNotResolved`.
- `TENANCY_ENABLED=false` by default (`config/tenancy.php`).
- Looking across tenants on purpose (for example platform-owner reports) needs `withoutGlobalScope(TenantScope::class)`, which is easy to spot in review.
- A cross-tenant test suite (`tests/Feature/Tenancy`) proves that a record of college A is invisible and untouchable for college B for users and the audit log, through models and through the API (a cross-tenant id answers 404), and that a real token works without a tenant being known first.

Decisions made along the way:

- **Email stays unique across the whole system.** Sign-in has no tenant yet, so a per-tenant rule would make the email ambiguous. It becomes `(tenant_id, email)` when a second college is onboarded.
- **Not scoped:** `roles`, `permissions` (the same roles apply everywhere; a user's roles are reached through the scoped user), `invitations`, `personal_access_tokens`, `password_reset_tokens` (they hang off a user, who carries the tenant), and Laravel infrastructure tables.
- **Tokens find their owner without a tenant:** a custom `PersonalAccessToken` model loads the token owner without the scope, because the token is how we learn the tenant. The `tenant` middleware then sets it.
- **Model events must stay on while seeding.** `DatabaseSeeder` used Laravel's `WithoutModelEvents`, which silently disables the hook that stamps `tenant_id`; the NOT NULL column caught it. The trait was removed and a test guards it.

## Alternatives considered

- **Separate database per college:** strongest isolation, but much more to run and back up for a handful of colleges; can still be chosen later because the scope keeps data separable.
- **Add tenancy later:** cheaper now, costly and risky later (see Context).
- **A tenancy package:** more features than needed; a few small classes are easier to review for a security-critical rule.

## Consequences

- Every new table and model carries `tenant_id` and the trait from the start. Unique rules on business values (course code, index number) are per tenant.
- **Do not switch tenancy on yet.** Requests that have no signed-in user (sign-in, password reset, accepting an invitation) cannot find their tenant, so they fail closed. Before a second college goes live we need: host-based tenant resolution for those requests, tenant-aware email uniqueness, a tenant option on `lems:create-super-admin` and the seeders, and a re-run of the tenancy tests with the real resolver.
- Seeders, console commands and tests that create data while tenancy is on must say which tenant (`TenantContext::runAs`).
