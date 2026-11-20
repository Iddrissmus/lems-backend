# Database diagram (ERD)

The first diagram shows the tables that **exist today**. Part 2 is the data model for the Slice 0 demo (student registration, mark entry, release, student results). **The tables, models, factories, synthetic data and the grade lock are built** (migrations of 2026-11-20); the endpoints on top of them are not, and field names will be adjusted to the frontend team's (D3). Each later phase adds its tables here in the same change as its migrations.

Notes that apply to every table:

- **Tenancy:** every domain table has a non-null `tenant_id` (foreign key to `tenants`) and every query is limited to the current tenant. It is built in and switched off: one default tenant owns everything (ADR 0010). The diagrams below omit `tenant_id` from the new tables to stay readable, but each of them has it, and any unique rule on a business value (for example a course code) is unique **per tenant**.
- Spelling follows the frontend and the contract: "programme", not "program".

- `id` is an internal number. The API only ever shows the public `ulid` (26 characters) and never accepts the number.
- Times are stored in UTC.
- Business records use soft deletes (`deleted_at`). `audit_logs` is append-only and never changes.
- The default Laravel infrastructure tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations`) are left out.

```mermaid
erDiagram
    tenants {
        bigint id PK
        char ulid UK "public id"
        string name
        string slug UK "scnmtc is the default tenant"
        string status
    }

    users {
        bigint id PK
        char ulid UK "public id"
        string name
        string email UK
        timestamp email_verified_at
        bigint tenant_id FK "which college owns this user"
        string password "null until an invitation is accepted"
        boolean must_change_password "true for issued accounts until the user picks their own"
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
        bigint tenant_id FK "which college owns this entry"
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

    tenants ||--o{ users : "owns"
    tenants ||--o{ audit_logs : "owns"
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

## Tenancy in the tables above

`tenants` and `tenant_id` (on `users` and `audit_logs`) were added for multi-tenancy (ADR 0010).

Not tenant-scoped on purpose: `roles`, `permissions` and their links (the same eight roles apply to every college; a user's roles are reached through the user, who is scoped), `invitations`, `personal_access_tokens` and `password_reset_tokens` (they belong to a user, who carries the tenant), and Laravel's own infrastructure tables.

## How to read the access model

A user holds one or more **roles**. A role holds **permissions**. The API checks permissions (for example `users.invite`), never role names, except for one rule: `super_admin` passes every check. `model_has_permissions` (a permission given straight to a user) exists because the permission package creates it, but the application does not use it.

`audit_logs.subject_type` and `subject_id` point at the record that was acted on by a short type name and its public id. There is deliberately no foreign key: the history must survive even if the record is later removed.

## Part 2 - Slice 0 data model (built; endpoints still to come)

The demo flow: staff register a student; the student registers for courses; the lecturer enters, reviews and submits marks (which locks them); the Head of Academics releases the course's results; the student sees letter grades. See ADR 0011 for the decisions behind this design.

```mermaid
erDiagram
    departments {
        bigint id PK
        char ulid UK
        string name
        string code "unique per tenant"
    }
    programmes {
        bigint id PK
        char ulid UK
        bigint department_id FK
        string name
        string code "unique per tenant"
        tinyint duration_years "nullable"
    }
    academic_years {
        bigint id PK
        string name "2026/2027, unique per tenant"
        date starts_on
        date ends_on
        boolean is_current
    }
    semesters {
        bigint id PK
        char ulid UK
        bigint academic_year_id FK
        tinyint number "1 or 2"
        date starts_on "first-class and audited: billing will read it"
        tinyint length_weeks "16, the contract definition of a semester"
        date ends_on "derived from starts_on and length_weeks"
        boolean is_current
    }
    cohorts {
        bigint id PK
        char ulid UK
        bigint programme_id FK
        string name "for example Class of 2029"
        smallint intake_year
        tinyint current_year_of_study
    }
    courses {
        bigint id PK
        char ulid UK
        bigint department_id FK
        string code "unique per tenant"
        string title
        decimal credit_hours
    }
    programme_courses {
        bigint id PK
        bigint programme_id FK
        bigint course_id FK
        tinyint year_of_study
        tinyint semester_number
        string curriculum_version "courses are versioned across curriculum review (BE-016)"
        boolean is_required
    }
    course_offerings {
        bigint id PK
        char ulid UK
        bigint course_id FK
        bigint semester_id FK
        date exam_date "nullable"
        timestamp marks_submitted_at "set when the lecturer submits: scores lock"
        bigint marks_submitted_by FK
        timestamp results_released_at "set by the Head of Academics: students can see"
        bigint results_released_by FK
    }
    registration_windows {
        bigint id PK
        char ulid UK
        bigint semester_id FK
        datetime opens_at
        datetime closes_at
        json audience "programmes and years; empty means everyone"
        datetime closed_at "closed early by staff"
        bigint closed_by FK
        bigint created_by FK
    }
    staff_profiles {
        bigint id PK
        bigint user_id FK "one-to-one with users"
        string staff_number "unique per tenant"
        bigint department_id FK "nullable"
    }
    student_profiles {
        bigint id PK
        char ulid UK
        bigint user_id FK "one-to-one with users"
        string index_number "unique per tenant, collision-safe"
        string moh_membership_id "cross-reference only, never a key"
        bigint programme_id FK
        bigint cohort_id FK
        tinyint year_of_study
        string status "active for the demo; state machine later (BE-011)"
        tinyint repeat_count
        string photo_path "nullable"
    }
    index_number_sequences {
        bigint id PK
        bigint programme_id FK
        smallint year
        int last_serial "row-locked when issuing a number"
    }
    lecturer_assignments {
        bigint id PK
        bigint course_offering_id FK
        bigint staff_profile_id FK
    }
    enrolments {
        bigint id PK
        char ulid UK
        bigint student_profile_id FK
        bigint course_offering_id FK
        string status "registered or withdrawn"
        timestamp registered_at
    }
    grade_scales {
        bigint id PK
        char ulid UK
        string name
        boolean is_active
        boolean is_demo "true for the synthetic demo scale: not the College's rules"
    }
    grade_scale_bands {
        bigint id PK
        bigint grade_scale_id FK
        string letter
        decimal min_score
        decimal max_score
        decimal points "nullable: GPA is not computed until the College confirms its metric"
    }
    assessments {
        bigint id PK
        char ulid UK
        bigint course_offering_id FK
        string name "one called Overall, weight 100, for the demo"
        decimal weight_percent
        decimal max_score
    }
    assessment_scores {
        bigint id PK
        char ulid UK
        bigint assessment_id FK
        bigint enrolment_id FK
        smallint version "1, then 2, 3... for each correction"
        decimal score "never shown to students"
        string letter_grade "stored when submitted, with the scale used"
        bigint grade_scale_id FK
        string status "draft or submitted (locked)"
        bigint entered_by FK
        timestamp submitted_at
        bigint submitted_by FK
        bigint supersedes_id FK "the version this one corrects"
        text reason "required for version 2 and up"
        bigint approved_by FK "who authorised a correction"
    }

    departments ||--o{ programmes : "has"
    departments ||--o{ courses : "owns"
    programmes ||--o{ cohorts : "intakes"
    programmes ||--o{ programme_courses : "curriculum"
    courses ||--o{ programme_courses : "taught in"
    academic_years ||--o{ semesters : "has two"
    courses ||--o{ course_offerings : "offered as"
    semesters ||--o{ course_offerings : "in"
    semesters ||--o{ registration_windows : "opens registration for"
    users ||--o| staff_profiles : "is"
    users ||--o| student_profiles : "is"
    programmes ||--o{ student_profiles : "studies"
    cohorts ||--o{ student_profiles : "belongs to"
    programmes ||--o{ index_number_sequences : "numbers"
    course_offerings ||--o{ lecturer_assignments : "taught by"
    staff_profiles ||--o{ lecturer_assignments : "teaches"
    student_profiles ||--o{ enrolments : "registers"
    course_offerings ||--o{ enrolments : "has"
    grade_scales ||--o{ grade_scale_bands : "has"
    course_offerings ||--o{ assessments : "graded by"
    assessments ||--o{ assessment_scores : "scored in"
    enrolments ||--o{ assessment_scores : "receives"
    grade_scales ||--o{ assessment_scores : "applied"
```

Also built: `audit_logs.reason` (text, nullable). Grade changes must say who, when and **why**.

The tables were checked against the frontend's admin dashboard (`docs/frontend-portal-review.md`): that is where `registration_windows` and `offerings.exam_date` come from. Two MariaDB 10.4 details worth knowing: `NOT NULL timestamp` columns get odd defaults there, so event times use `dateTime`; and index names must stay under 64 characters, so the long ones are named by hand.

### How marks and results move

```mermaid
stateDiagram-v2
    [*] --> Draft: lecturer saves marks
    Draft --> Draft: lecturer edits (allowed while draft)
    Draft --> Submitted: lecturer submits the course
    Submitted --> Released: Head of Academics releases
    Released --> Released: Academic Affairs correction adds a new version
    note right of Submitted
        Locked. No later write to these rows is accepted:
        423 grade_locked, and the database refuses it too.
    end note
```

- **Locking is enforced in the database,** not only in the code (built, and tested with raw SQL): the triggers `assessment_scores_locked_update` and `assessment_scores_locked_delete` reject any UPDATE or DELETE of a score row once its status is `submitted`. A correction is a **new row** (higher `version`, `supersedes_id`, a `reason`), never an edit. The current grade is the latest approved version.
- **What a student sees:** only the current submitted version of their own enrolment's scores, and only when the offering's `results_released_at` is set. The API returns the letter grade and credit hours, never the numeric `score` (BE-027).
- **Who may do what (permissions to add when built):** `students.register`, `enrolments.manage`, `marks.enter` (only for offerings the lecturer is assigned to, enforced by a query scope on `lecturer_assignments`), `marks.submit`, `results.release` (head_of_academics), `results.correct` (academic_affairs), `results.view-own` (student, own rows only).

### Open points (nothing here is decided by guesswork)

| # | Question | For the demo | Who decides |
|---|---|---|---|
| 1 | Is a mark one number, or continuous assessment plus exam with weights? The portal mentions "default weights" | One assessment called Overall (weight 100). The tables already allow several | Frontend files and the College |
| 2 | Grade scale and the GPA/CGPA metric (D5) | A synthetic scale flagged `is_demo`; no GPA; `points` empty | The College |
| 3 | Course-registration rules: credit limits, clashes, prerequisites, arrears gating (BE-065) | Not stored or enforced; registration only checks the course is offered in the current semester and not already taken | The College |
| 4 | Student status values and transitions (BE-011) | Only `active` | The College |
| 5 | Index number format (BE-008: abbreviation, programme, year, serial) | Placeholder `<programme code>/<year>/<4-digit serial>`, issued from `index_number_sequences` under a row lock | The College |
| 6 | Email is unique across the whole system today | Kept (sign-in has no tenant yet); becomes per-tenant when a second college is onboarded (ADR 0010) | Owner |
| 7 | Field names and shapes | Proposed; will follow the frontend's `app/Data` objects (D3) | Frontend engineer and owner |
| 8 | Registration window audience and statuses | `audience` is a JSON list; status is derived (not yet open, open, closed) | Frontend engineer |

### Later (not part of Slice 0)

Fees (`fee_schedules`, `fee_items`, `invoices`, `invoice_lines`, `payments` as append-only counter-entries in integer pesewas, `receipts`), announcements (`announcements`, `announcement_audiences`, `announcement_reads`), notifications, admissions and applicants, contestations, evaluations, clinical placements, stores, and files. Each is added with its own migration and its own section here.
