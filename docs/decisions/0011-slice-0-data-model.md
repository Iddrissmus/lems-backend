# 0011 - Slice 0 data model: students, marks, locking and release

Date: 2026-11-20 | Status: **Accepted for the foundations**, which are built; endpoints and field names still follow the frontend team's contract (D3) | Decided by: Iddriss (flow and a single Overall mark per course), Claude (design)

## Context

The Monday demo flow is: staff register a student; the student registers for courses; the lecturer enters, reviews and submits marks; the Head of Academics releases the results; the student sees them. The contract adds hard requirements: submitted marks lock with no override (BE-023), corrections are new audited versions with a reason (BE-025/026), students see letter grades and never percentages (BE-027), the semester start date is a first-class audited field and a semester is exactly 16 weeks (BE-019), the Ministry of Health membership id is never a key (BE-009), and index numbers are collision-safe (BE-008). The full diagram and open points are in `docs/erd.md` (Part 2).

## Decision

- **Identity stays separate from academic data.** `users` holds sign-in only; `student_profiles` and `staff_profiles` (one-to-one with a user) hold the academic facts. A person can later have both without duplicating accounts.
- **Courses are offered per semester.** `courses` is the catalogue; `programme_courses` links a course to a programme, year and semester and is versioned by `curriculum_version`; `course_offerings` is a course delivered in one semester. Lecturers are assigned to offerings (`lecturer_assignments`) and students register into offerings (`enrolments`).
- **Marks are immutable once submitted.** `assessment_scores` rows have `status` draft or submitted. While draft a lecturer may change them. Submitting a course sets `course_offerings.marks_submitted_at` and the rows become submitted. A database trigger then rejects any UPDATE or DELETE of a submitted row, and the API answers `423 grade_locked`. A correction is a new row with a higher `version`, `supersedes_id` and a required `reason`, created through an Academic Affairs route; the current grade is the latest approved version. Nothing is edited in place.
- **Release is a separate step.** The Head of Academics sets `course_offerings.results_released_at`; lecturer submission alone publishes nothing (the portal already states this rule). A student sees only the current submitted version of their own scores, only after release, as letter grade and credit hours. The numeric score never leaves the API.
- **Letter grades are stored when submitted,** together with the grade scale used, so changing a scale later cannot change published results.
- **Grade scale is configuration** (`grade_scales`, `grade_scale_bands`). The demo uses a clearly flagged synthetic scale (`is_demo`). No GPA is computed (`points` stays empty) until the College confirms its metric (D5).
- **One assessment for the demo.** The tables allow continuous assessment and exam components with weights (`assessments`), but the demo seeds a single "Overall" assessment per offering, because the College's rules are not confirmed.
- **Index numbers** are issued from `index_number_sequences` under a row lock. The format is a placeholder until the College confirms it. `moh_membership_id` is stored for cross-reference only, with no unique constraint and no foreign key.
- **Semesters** store `starts_on`, `length_weeks` (16) and the derived `ends_on`; changing the start date is audited.
- **Every table has `tenant_id`** and uses `BelongsToTenant` (ADR 0010). Unique rules on business values are per tenant.
- **Spelling follows the frontend:** "programme".
- **Public ids** are ULIDs on every record the API exposes; numeric ids never appear.

## Alternatives considered

- **Marks as a single column on the enrolment:** simplest, but cannot hold correction history and cannot lock safely at the database level.
- **Lock in the controller only:** the contract requires enforcement that no later endpoint can write around.
- **Compute letter grades at read time:** a scale change would silently rewrite published results.
- **Merge student and user into one table:** breaks for people who are both staff and students, and mixes sign-in data with records the College must retain after deactivation.

## Consequences

- About 17 new tables and several permissions (`students.register`, `enrolments.manage`, `marks.enter`, `marks.submit`, `results.release`, `results.correct`, `results.view-own`). Lecturer access is limited by a query scope on assignments, not only by controllers.
- If the College or the frontend needs several weighted components per course, the tables already support it; the "course grade" calculation then needs the College's rules and a `course_results` table.
- Triggers are MySQL/MariaDB specific; the production engine decision (ADR 0006) must be settled before deployment.
- Open points that need the College or the frontend are listed in `docs/erd.md`. Nothing in this record invents a business rule.

## Update 2026-11-20: built, and changes after reviewing the admin dashboard

The 17 tables, models, factories, a synthetic seeder and the grade-lock triggers are built (migrations `2026_11_20_1300xx`), with 62 tests (tenant isolation for every model, every unique rule, index numbers, the lock at application and database level, the seeder).

Changes from the proposal, all driven by `docs/frontend-portal-review.md`:

- **`registration_windows`** (new, 18th table): registration opens and closes in windows with an audience, not as two dates on the semester. The two date columns on `semesters` were dropped.
- **`course_offerings.exam_date`** added (the department dashboard shows it).
- **`audit_logs.reason`** added (grade changes carry the why).
- **Event times are `dateTime`,** not `timestamp` (MariaDB 10.4 gives NOT NULL timestamps invalid defaults and auto-updates).

Not built yet: the endpoints, the permission names (the frontend already uses colon names such as `marks:enter`; see the review page), policies, `Idempotency-Key` handling, the mapping of the database's `grade_locked` error to HTTP 423, student status requests (BE-011) and CSV import (BE-013).
