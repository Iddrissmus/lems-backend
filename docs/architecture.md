# Architecture

How the backend is built and why. The rules come from `setup.md`; this page describes what exists in the code today.

## The big picture

```
Frontend (separate Laravel app)
        |  HTTPS, JSON, "Authorization: Bearer <token>"
        v
   /api/v1/...  ->  Laravel API  ->  MySQL
                        |
                        +--> mail (invitations, password resets)
```

The frontend never touches the database. Everything it can do is an endpoint listed in the OpenAPI document (`docs/api/openapi.json`).

## One request, step by step

A request passes through the same layers every time, and each layer has one job:

| Layer | Where | Job |
|---|---|---|
| Route | `routes/api.php` | Maps a URL and method to a controller. Adds middleware: `auth:sanctum` (valid token), `active` (account not disabled), `password.changed` (the user has chosen their own password; `/me`, change password and logout are exempt), `throttle`. |
| Form Request | `app/Http/Requests/` | Validates and cleans the input (for example lower-cases emails). Nothing else. |
| Controller | `app/Http/Controllers/` | Thin. Checks permission with `Gate::authorize(...)`, calls one Action, returns a Resource. No business rules. |
| Policy | `app/Policies/` | Answers "may this user do this?". Runs on every protected endpoint. |
| Action | `app/Domain/<Area>/Actions/` | The business logic, one class per use case (`InviteUser`, `AssignRoles`, ...). Wraps changes in a database transaction. |
| Model | `app/Models/` | Database access only. |
| Resource | `app/Http/Resources/` | The shape of the JSON that goes out. Models are never returned directly, so a new column cannot leak by accident. |

Example: `PUT /users/{id}/roles` goes through `AssignRolesRequest` (are the roles valid?), then `UserRolesController` (does this user have `users.assign-roles`, and may they grant these roles?), then the `AssignRoles` action (transaction, last super admin check, save, audit event), then `UserResource`.

## Folder map

```
app/
  Domain/                    business logic grouped by area
    Identity/                accounts, sign in, roles
      Actions/  Enums/  Events/
    Audit/                   the audit trail
  Http/
    Controllers/  Requests/  Resources/  Middleware/
  Models/  Policies/  Notifications/  Exceptions/  Console/Commands/
  Support/Api/               error format and API documentation helpers
config/  database/  routes/  tests/  docs/
```

New areas (Academics, Results, Fees, Communication, Admissions) get their own folder under `app/Domain/` with the same layout.

## Rules that hold everywhere

- **Versioned and JSON only.** Everything is under `/api/v1`. A breaking change inside `/v1` is not allowed once the frontend depends on it; it needs `/v2`.
- **One error format.** Every error is `{message, errors, code}` (`app/Support/Api/ErrorResponder.php`). The frontend matches on `code`. See ADR 0003.
- **Authorization on the server.** Hiding a button in the UI is a convenience, never the protection. Every endpoint checks a policy.
- **Public ids are ULIDs.** Numeric ids stay inside the database.
- **Transactions.** Anything that changes roles, money or published results runs in a database transaction.
- **Audit everything that matters.** Actions dispatch an event inside the same transaction; the audit subscriber turns it into an `audit_logs` row. The change and its audit entry are saved together or not at all. See ADR 0004.
- **No N+1 queries.** Lists eager-load what they show; tests count queries on the busy endpoints.
- **Lists are paginated** with documented limits (`per_page` max 100).
- **Strict database mode** stays on in every environment. Never rely on implicit type conversion.
- **Money is stored as whole pesewas** (integers), never decimals or floats. (Applies from Phase 4.)
- **Time** is stored in UTC. The college's time zone (Africa/Accra, same as UTC with no daylight saving) only matters for display.
- **Secrets** live in environment variables. `.env` is never committed; `.env.example` lists every key.

## Events

Actions announce what happened with plain event classes (`app/Domain/Identity/Events/`): `UserInvited`, `UserRolesChanged`, `UserStatusChanged`, `UserLoggedIn`, and so on. Listeners react.

- The **audit subscriber** is synchronous on purpose, so it joins the transaction.
- Listeners that send email or call another service must be **queued** and added separately, so an outage there can never block or undo a change.

## Quality gate

`composer ci` runs Pint (code style), Larastan level 6 (static analysis) and the Pest tests against a freshly migrated MySQL test database. It must pass before anything is merged, and a git pre-push hook runs it automatically. One of the tests fails if `docs/api/openapi.json` is out of date.

## Where to look next

- `docs/erd.md` - the database tables
- `docs/api/README.md` - the API contract and how to view it
- `docs/api/frontend-guide.md` - what the frontend team needs
- `docs/decisions/` - why things are the way they are
- `docs/manual-testing.md` - try the API yourself
- `docs/PROJECT_LOG.md` - what was done and when
