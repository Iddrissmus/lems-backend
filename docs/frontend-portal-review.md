# What the frontend already has (review of 2026-11-20)

A read-only look at the frontend demo app (`thescnmtc-lms.fly.dev`, titled "(demo)") as a student and as an administrator, with synthetic data confirmed by the owner. Only screen names and field names were noted; no personal data and no credentials are recorded here. It is **not** a contract: the frontend engineer's own contract files (`docs/frontend/contracts`, `app/Contracts`, `app/Data`) are the authority once received (D3). Use this page to understand what his screens expect and what it means for the API.

## The app

A Laravel **Livewire** application with its own login (Fortify, passkeys) and its own demo data. Its audit screen shows internal action names such as `processStudent` and `processFrontendSetup`: the app currently performs these actions itself. Connecting to our API means replacing those with calls to us, behind the interfaces he mentioned.

## Student screens

Dashboard, Announcements (sources: All, My courses, Academic, Clinical, SRC), **Course registration** (filters: department, programme, year, semester, search; select courses; a registration can be rejected with a reason), Results ("View only"; appear only after the **Head of Academics releases** them; resit results), Transcript (sample 4.0 GPA, weighted average %, credits completed, NON-OFFICIAL), profile, passport photo.

## Admin and staff screens

| Screen | What it does (fields seen) | Backend relevance |
|---|---|---|
| General dashboard | Department dashboards: Academics, IT, Administration, Human Resources, Procurement, Finance (Accounts) | Only Academics and Accounts map to the backlog |
| **Add an account** | Account type (7), name, email (with a suggestion), programme, year, school/department, courses to register, then shows the created password and index number once | Staff create the student record and portal account; index number issued at creation; temporary password with forced change (ADR 0009) |
| Accounts dashboard | Counts and "recently created" accounts | Read endpoints on users and students |
| **Password resets** | Staff, student and lecturer accounts, status "awaiting"; admin resets a password and is shown it | New endpoint: admin issues a temporary password (sets `must_change_password`) |
| **Registration setup** | Registration windows: academic year, semester, opens at, closes at, audience; filter by year, semester, status; close a window | New table `registration_windows`; registration is only possible while a window is open for the student's audience |
| Courses | Programmes with their courses, credits and duration ("Registered General Nursing, 3 years"); set up and manage semester courses | Programmes, courses, curriculum links, offerings |
| Students | Search by department, programme, year, cohort; student records; edit with a reason; **status-change requests** (requested status, effective date, reason, rejection reason; pending, approved, rejected); **CSV import** | BE-010, BE-011, BE-013. Not needed for Monday |
| Academics dashboard | "Mark entry by course": course, lecturer, class, marks entered, status (Not started, In draft, Submitted), exam date | Offering needs an exam date; status is derived from the marks and the offering's submit and release timestamps |
| Graduation list | Index number, name, programme, year, eligibility | BE-031 (Could). Not for Monday |
| **Roles and permissions** | Per role, toggles for these permissions: `admissions:view/import/approve/collect`, `announcements:manage`, `registration:manage`, `students:status:request`, `students:status:review`, `courses:teach`, `directory:view`, `academics:dashboard:view`, `marks:enter`, `marks:submit`, `marks:correct`, `accounts:create`, `accounts:reset-password` | The permission vocabulary every screen consumes (BE-002, a shared decision). Theirs uses colons; ours uses dots. There is **no release permission** in their list |
| Audit log | Filters by area, action, search and dates; "View changes" | Matches our audit log, which also records before and after |
| Staff directory, appointments, leave requests, department tasks | Human-resources screens | **No backlog item (no BE id).** Not built; flagged per the working agreement |

## What this changes for the backend

1. **Registration windows** are a real concept (not just two dates on a semester): the data model gets `registration_windows`.
2. **Mark entry status per course** needs `exam_date` on the offering and a derived status (not started, draft, submitted, released).
3. **Idempotency:** the workspace carries an `operationKey`, so writes (registration, submit, release) should accept an `Idempotency-Key` header.
4. **Account creation by staff** matches our forced password change; the API must return the temporary password exactly once and never write it to the audit log.
5. **Permission vocabulary** must be agreed (BE-002). Proposal: adopt the frontend's names for the permissions they already show (`marks:enter`, `marks:submit`, `marks:correct`, `accounts:create`, `accounts:reset-password`, `registration:manage`, ...) and add `results:release`; migrate our own `users.*` and `audit.view` to the same style while nothing depends on them yet.
6. **Out of backlog:** staff appointments, leave, department tasks and the Procurement, Finance, IT and HR dashboards. We do not build them without a Change Request.

## Questions for the frontend engineer

1. Which permission names are final? Where does the Head of Academics' release live (a permission of its own, or part of `academics:dashboard:view`)?
2. Is the Idempotency-Key header (your `operationKey`) acceptable for writes?
3. Should the API create the student record and the portal account in one call (as your "Add an account" screen does)?
4. Registration windows: can a window target several programmes and years (your `formAudience` is a list)? What are the possible window statuses?
5. Mark entry: one total mark per student per course, or components with weights (your "app default weights")?
