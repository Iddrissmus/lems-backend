# 0005 - Roles hold permissions; who manages accounts

Date: 2026-11-18 | Status: Accepted | Decided by: Iddriss

## Context

The brief lists six roles. The API needs a clear rule for who may invite users, change roles and disable accounts, without inventing college rules the owner has not confirmed.

## Decision

- Use `spatie/laravel-permission`. Roles and permissions are PHP enums (`Role`, `Permission`) so names cannot be mistyped. A seeder creates them and is safe to run on every deploy.
- The API checks **permissions** (`users.invite`, `audit.view`, ...), not role names. Roles are bundles of permissions.
- `super_admin` passes every check (`Gate::before`). `head_of_academics` holds all `users.*` permissions. **Every other role starts with no permissions**; each module grants its own when built, after the college confirms the rule.
- Only a `super_admin` can grant the `super_admin` role or change a super admin's account.
- A user may hold several roles at once.
- Safety rules: you cannot disable yourself; the last active `super_admin` can never be disabled or demoted.
- The first `super_admin` is created from the terminal with `php artisan lems:create-super-admin` (the password is typed, never stored in code). Demo accounts exist only in the local environment.

## Alternatives considered

- **Roles checked directly in code:** simpler, but every change to what a role may do means editing code in many places.
- **Everyone with `users.invite` can grant any role:** lets an administrator promote themselves or a friend to super admin.

## Consequences

- Giving a role a new ability is a one-line change in `Role::permissions()`.
- The owner approved `head_of_academics` managing accounts. Revisit if the college wants this narrower (for example, not managing students).
