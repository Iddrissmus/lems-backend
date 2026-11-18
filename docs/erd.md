# Database diagram (ERD)

Tables that exist today (Phase 1). Each later phase adds its own tables here in the same change as its migrations.

Notes that apply to every table:

- `id` is an internal number. The API only ever shows the public `ulid` (26 characters) and never accepts the number.
- Times are stored in UTC.
- Business records use soft deletes (`deleted_at`). `audit_logs` is append-only and never changes.
- The default Laravel infrastructure tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations`) are left out.

```mermaid
erDiagram
    users {
        bigint id PK
        char ulid UK "public id"
        string name
        string email UK
        timestamp email_verified_at
        string password "null until an invitation is accepted"
        string status "invited, active, disabled"
        timestamp last_login_at
        string remember_token
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    invitations {
        bigint id PK
        bigint user_id FK
        bigint invited_by_id FK "null if system"
        char token_hash UK "sha256, the token itself is never stored"
        timestamp expires_at
        timestamp accepted_at
        timestamp created_at
        timestamp updated_at
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type "user"
        bigint tokenable_id FK
        text name "device name"
        string token UK "hashed"
        text abilities
        timestamp last_used_at
        timestamp expires_at
    }

    password_reset_tokens {
        string email PK
        string token "hashed"
        timestamp created_at
    }

    roles {
        bigint id PK
        string name "super_admin, head_of_academics, ..."
        string guard_name
    }

    permissions {
        bigint id PK
        string name "users.view, audit.view, ..."
        string guard_name
    }

    model_has_roles {
        bigint role_id FK
        string model_type "user"
        bigint model_id FK
    }

    model_has_permissions {
        bigint permission_id FK
        string model_type "user"
        bigint model_id FK
    }

    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }

    audit_logs {
        bigint id PK
        char ulid UK "public id"
        string event "user.roles_changed, auth.login, ..."
        bigint actor_id FK "null if system or not signed in"
        string subject_type "user"
        char subject_id "public id of the record"
        json before
        json after
        string ip_address
        string user_agent
        timestamp created_at
    }

    users ||--o{ invitations : "is invited by"
    users |o--o{ invitations : "sent"
    users ||--o{ personal_access_tokens : "signs in with"
    users ||--o{ model_has_roles : "holds"
    roles ||--o{ model_has_roles : "given to"
    users ||--o{ model_has_permissions : "holds directly (unused)"
    permissions ||--o{ model_has_permissions : "given to"
    roles ||--o{ role_has_permissions : "includes"
    permissions ||--o{ role_has_permissions : "granted by"
    users |o--o{ audit_logs : "did"
```

## How to read the access model

A user holds one or more **roles**. A role holds **permissions**. The API checks permissions (for example `users.invite`), never role names, except for one rule: `super_admin` passes every check. `model_has_permissions` (a permission given straight to a user) exists because the permission package creates it, but the application does not use it.

`audit_logs.subject_type` and `subject_id` point at the record that was acted on by a short type name and its public id. There is deliberately no foreign key: the history must survive even if the record is later removed.

## Planned (not built yet)

From the project brief, added phase by phase: `student_profiles`, `staff_profiles`, `programs`, `cohorts`, `academic_years`, `semesters`, `courses`, `course_offerings`, `enrolments` (Phase 2); `assessments`, `assessment_scores`, `semester_results`, `grade_scales` (Phase 3); `fee_structures`, `fee_items`, `invoices`, `invoice_lines`, `payments`, `payment_allocations`, `receipts` (Phase 4); `notices`, `notice_audiences`, `notice_reads`, `notifications` (Phase 5); `files` (as needed).
