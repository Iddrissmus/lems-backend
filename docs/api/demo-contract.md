# API contract for the demo flow (DRAFT v0)

**Status:** draft for review by the frontend engineer, 2026-11-20. Written by the backend; the frontend corrects it. **Built and tested (2026-11-20): sections 3 to 6** (reference data, registration windows, staff registering students and password resets, the student's own course registration) and the `Idempotency-Key` rule, on the branch `phase-1/registration`. **Also built and tested (2026-11-21): sections 7 to 9** (lecturer marks, release, student results, the grade scale), on the branch `phase-1/marks`; this page is the proposed freeze for them. **Agreed on 2026-11-21 (owner, see ADR 0012):** password-only sign-in through our API for Monday (no passkeys or 2FA), one Overall mark per course, colon-style permission names, and sample GPA calculated by the frontend from `GET /grade-scale`. Sign-in, users, roles and the audit log (section 1) were already built. Where a built endpoint differs from what this draft first said, the text says "As built". After you reply we **freeze** it; once built, the live OpenAPI document (`docs/api/openapi.json`, served at `/docs`) replaces this page. After the freeze, changes are additive only.

**The flow it covers:** staff register a student, the student registers for courses, the lecturer enters, reviews and submits marks, the Head of Academics releases them, the student sees letter grades.

**How to review:** read sections 3 to 9. Anything marked **(A#)** is an assumption I made (listed in section 13); anything marked **(Q#)** is a question I need answered (listed in section 14). Change what is wrong in place and send it back.

## 1. Rules that already hold (built and tested)

| Topic | Rule |
|---|---|
| Base URL | `https://<api host>/api/v1` |
| Headers | `Accept: application/json` always; `Content-Type: application/json` with a body; `Authorization: Bearer <token>` after sign-in |
| Sign-in | **Agreed (Q1):** our API owns the accounts; your server calls login and keeps the token in its own session. Password only for Monday; Fortify passkey and 2FA flows are not used. `POST /auth/login` `{email, password, device_name?}` gives `data.token`, `data.expires_at` (12 hours) and `data.user` with `roles`, `permissions`, `must_change_password`. Full details: `docs/api/frontend-guide.md` |
| Success | `{ "data": ..., "meta": ... }`. Lists add `links` and `meta` (`current_page`, `per_page`, `total`, `last_page`); `?page=` and `?per_page=` (max 100) |
| Errors | `{ "message", "errors": {field: [messages]}, "code" }`. Decide by status and `code`, never by `message`. 401 `unauthenticated`, 403 `forbidden`, 404 `not_found`, 409 `conflict`, 422 `validation_failed`, 423 `grade_locked`, 429 `too_many_requests` |
| Ids | 26 character lower-case ULIDs, opaque strings. Numeric ids are never accepted |
| Names | `snake_case` JSON fields. British spelling: `programme` |
| Dates | Times: ISO 8601 UTC (`2026-11-20T14:30:00+00:00`). Calendar dates: `2026-12-14`. Ghana time equals UTC |
| Numbers | Marks and credit hours are JSON numbers (`72.5`, `3`). Never strings |
| Forced password change | While `must_change_password` is true, everything except `GET /me`, `POST /auth/password/change` and `POST /auth/logout` answers 403 `password_change_required` |
| Existing endpoints | `GET /me`, `GET /roles`, `GET/PATCH /users`, `POST /users/invite`, `PUT /users/{id}/roles`, `POST /users/{id}/disable` and `/enable`, `GET /audit-logs`, `GET /health` |

### New cross-cutting rules (proposed)

- **`Idempotency-Key` header** (your `operationKey`) on every `POST` and `PUT` below. Same key and same body returns the original response again with the header `Idempotent-Replayed: true` and does nothing twice. Same key with a different body answers 422 `idempotency_key_reused`. Keys are remembered for 24 hours. Send a fresh random key (a UUID) per user action, and reuse it only when retrying that action. **(Q13)**
- **A student sees only their own data.** Staff see only what their permission allows. The filter is in the API, never only in the screens.
- **Letter grades only for students.** Numeric marks (`score`) are visible to the lecturer of that course and to staff with the right permission, never to a student.

## 2. Roles and permissions (proposed)

`GET /me` returns `permissions` (an array of names). Built so far: `accounts:create`, `accounts:reset-password`, `students:view`, `registration:manage`, `registration:self`; the rest arrive with their endpoints. Hide or show screens from permissions, not role names. I propose the colon style your "Roles & permissions" screen already uses; the permissions your screen does not have yet are marked **new**. **(Q2)**

| Permission | Meaning | Proposed holders |
|---|---|---|
| `accounts:create` | Create a student record and portal account | super_admin, ict_admin, registrar |
| `accounts:reset-password` | Issue a temporary password to a user | super_admin, ict_admin |
| `students:view` | Search and read student records | super_admin, head_of_academics, ict_admin, registrar, academic_affairs |
| `registration:manage` | Open and close registration windows | super_admin, head_of_academics |
| `academics:dashboard:view` | The academics dashboard (mark entry by course) | super_admin, head_of_academics, academic_affairs |
| `courses:teach` | See and work on the courses I am assigned to | lecturer |
| `marks:enter` | Save draft marks | lecturer |
| `marks:submit` | Submit marks (locks them) | lecturer |
| `marks:correct` | Create an authorised correction | academic_affairs |
| `results:release` **new** | Release a course's results to students | head_of_academics |
| `registration:self` **new** | Register for courses myself | student |
| `results:view-own` **new** | See my own released results | student |
| existing `users.*`, `audit.view` | Account management and audit log | unchanged (to be renamed to the same style when you confirm) |

A lecturer only ever sees courses they are assigned to, even with `courses:teach`. `GET /grade-scale` (section 9) needs only a signed-in, active user.

## 3. Reference data (read-only)

Used by the registration filters (department, programme, year, semester, search).

| Request | Answer |
|---|---|
| `GET /departments` | `data: [{id, code, name}]` |
| `GET /programmes?department=<id>` | `data: [{id, code, name, duration_years, department: {id, code, name}}]` |
| `GET /cohorts?programme=<id>` | `data: [{id, name, intake_year, current_year_of_study, programme_id}]` |
| `GET /semesters?current=1` | `data: [{id, academic_year: "2026/2027", number: 1, starts_on, ends_on, length_weeks: 16, is_current}]` |
| `GET /course-offerings?semester=<id>&programme=<id>&year=<n>&department=<id>&q=<text>` | Courses on offer, paginated: see below |

Course offering:

```json
{
  "id": "01jd2k9m4q7x3v8n5r6t1y0zac",
  "course": { "id": "01jd2k9m4q7x3v8n5r6t1y0zad", "code": "NUR 111", "title": "Foundations of Nursing", "credit_hours": 3, "department": { "id": "...", "code": "NUR", "name": "Nursing" } },
  "semester": { "id": "...", "academic_year": "2026/2027", "number": 1 },
  "classes": [ { "programme": { "id": "...", "code": "RGN", "name": "Registered General Nursing" }, "year_of_study": 1 } ],
  "exam_date": "2026-12-14"
}
```

`classes` is a list because one course (for example Study Skills) can serve several programmes. Permissions: any signed-in user with `students:view`, `registration:manage`, `academics:dashboard:view` or `registration:self`.

## 4. Registration windows

A window says when students may register. Registration is only possible while a window is open for the student's programme and year.

| Request | Permission | Notes |
|---|---|---|
| `GET /registration-windows?semester=<id>&status=<not_yet_open|open|closed>` | `registration:manage` | `data: [window]`, paginated |
| `POST /registration-windows` | `registration:manage` | Body below. 201 with the window |
| `POST /registration-windows/{id}/close` | `registration:manage` | Closes it early. 200 with the window. Closing a closed window is a 200 no-op |

```json
{ "semester_id": "01jd...", "opens_at": "2026-11-23T08:00:00+00:00", "closes_at": "2026-12-07T17:00:00+00:00",
  "audience": [ { "programme_id": "01jd...", "year_of_study": 1 } ] }
```

`audience` empty or omitted means everyone. A window:

```json
{ "id": "01jd...", "semester": { "id": "...", "academic_year": "2026/2027", "number": 1 },
  "opens_at": "...", "closes_at": "...", "closed_at": null,
  "audience": [ { "programme": { "id": "...", "code": "RGN", "name": "..." }, "year_of_study": 1 } ],
  "status": "open" }
```

`status` is computed: `not_yet_open`, `open`, `closed` (closed early, or past `closes_at`). **(A1, Q4)**

## 5. Staff register a student

| Request | Permission | Notes |
|---|---|---|
| `GET /students?q=&programme=&year=&cohort=&department=&page=` | `students:view` | `q` searches name, email and index number. Paginated |
| `GET /students/{id}` | `students:view` | One student |
| `POST /students` | `accounts:create` | Creates the portal account, the student record and the index number in one call. 201 |
| `POST /users/{id}/reset-password` | `accounts:reset-password` | Issues a new temporary password. 200 |

`POST /students` body (your "Add an account" screen):

```json
{ "name": "Ama Mensah", "email": "ama.mensah@example.com", "programme_id": "01jd...", "cohort_id": "01jd...",
  "year_of_study": 1, "moh_membership_id": null, "course_offering_ids": ["01jd...", "01jd..."] }
```

`moh_membership_id` and `course_offering_ids` are optional. **As built:** the student needs a cohort. Give `cohort_id`, or give `year_of_study` when exactly one cohort of that programme is in that year (otherwise 422 on `cohort_id`). `year_of_study` defaults to the cohort's current year. Staff registering first courses do not need an open registration window; if any course is refused nothing is created (the account, the record and the courses roll back together). Answer:

```json
{
  "data": {
    "id": "01jd2k9m4q7x3v8n5r6t1y0zab",
    "index_number": "RGN/2026/0042",
    "user": { "id": "01jd...", "name": "Ama Mensah", "email": "ama.mensah@example.com", "status": "active", "must_change_password": true },
    "programme": { "id": "...", "code": "RGN", "name": "Registered General Nursing" },
    "cohort": { "id": "...", "name": "RGN class of 2029", "intake_year": 2026 },
    "year_of_study": 1,
    "status": "active",
    "registered_offerings": 2
  },
  "meta": { "temporary_password": "Qk7x-vN2m-Rt9x" }
}
```

- `meta.temporary_password` is shown **once**, in this answer only. It is never stored in readable form, never returned again, and never written to the audit log. Show it to the staff member to hand over. The student is forced to change it at first sign-in.
- The index number is issued by the API (collision-safe). Its format is a placeholder (`<programme code>/<year>/<4-digit serial>`) until the College confirms its own. **(A2)**
- Email must be unique across the system: 422 `validation_failed` with `errors.email`. Your `emailSuggestion` stays a frontend feature; the API only validates. **(Q10)**
- `POST /users/{id}/reset-password` answers the user (with `must_change_password: true`) and `meta.temporary_password`. It signs the user out everywhere. **As built:** only for an **active** account (409 `conflict` for invited or disabled ones), not for yourself, and not for a `super_admin` unless you are one. The temporary password has the form `Qk7x-vN2m-Rt9x` (14 characters, no look-alike characters).
- **As built:** a retry with the same `Idempotency-Key` returns the same temporary password again (the saved answer is encrypted in the database), so a dropped connection does not lose it.
- Student record shape (`GET /students`, `GET /students/{id}`): the `data` object above without `meta`, plus `moh_membership_id`, `created_at`.

## 6. Student course registration

| Request | Permission | Notes |
|---|---|---|
| `GET /me/student` | `registration:self` (and `results:view-own` once it exists) | My profile: `{id, index_number, name, programme, cohort, year_of_study, status}` |
| `GET /me/registration` | `registration:self` | Is registration open for me, and what I have registered |
| `GET /me/course-offerings?q=` | `registration:self` | Courses I may register for: my programme's curriculum for my year in the current semester, with `registered: true/false` |
| `POST /me/registrations` | `registration:self` | Register for courses. 201 |
| `DELETE /me/registrations/{enrolment_id}` | `registration:self` | Withdraw, while the window is open. 200 (stretch) |

`GET /me/registration`:

```json
{ "data": {
    "semester": { "id": "...", "academic_year": "2026/2027", "number": 1 },
    "window": { "status": "open", "opens_at": "...", "closes_at": "..." },
    "registered": [ { "id": "01jd...", "course_offering_id": "01jd...", "code": "NUR 111", "title": "Foundations of Nursing", "credit_hours": 3, "registered_at": "..." } ]
} }
```

`window` is the one that matters to me: an open one, else the next to open, else the most recently closed, or `null` when none covers my programme and year. `semester` is `null` when there is no current semester. `POST /me/registrations`:

```json
{ "course_offering_ids": ["01jd...", "01jd..."] }
```

Answer 201: `data: [ { id, course_offering_id, code, title, credit_hours, registered_at } ]` (the new registrations). **All or nothing (A3, Q3):** if any course is refused, nothing is registered and the answer is 422:

```json
{ "message": "Some courses could not be registered.", "code": "registration_rejected",
  "errors": { "course_offering_ids.1": ["This course is not in your programme's curriculum for this semester."] } }
```

Reasons, each as a human sentence in `errors` (the frontend shows them as your `rejection`): registration is closed (422 `registration_closed`, `errors` empty), the course is not offered this semester, the course is not in the student's curriculum for their programme, year and semester, the student is already registered. **As built:** `DELETE /me/registrations/{id}` keeps the registration with status `withdrawn`, needs an open window, answers 409 `conflict` once marks exist for the course or if it is already withdrawn, and 404 for a registration that is not the student's own. Registering again for a withdrawn course reuses the same registration. **I do not enforce credit limits, clashes, prerequisites or arrears rules: the College has not confirmed them (BE-065).** **(A4)**

## 7. Lecturer: enter, review, submit

A lecturer sees only offerings they are assigned to. **One mark per student per course, called Overall, out of 100. (A5, Q5)**

**As built:** a course that is not yours answers 404 (also for `super_admin`: these routes are strictly for the assigned lecturer), and a role without the permission answers 403 first. `GET /lecturer/course-offerings` defaults to the current semester; `?semester=` takes a semester id and `?status=` is not applied here. Withdrawn students are not in the class list. The "Overall" assessment is created the first time a course's marks are read or saved, so a course never needs setting up by hand. Students are listed in index number order.

| Request | Permission | Notes |
|---|---|---|
| `GET /lecturer/course-offerings` | `courses:teach` | My courses, with status |
| `GET /lecturer/course-offerings/{id}/marks` | `courses:teach` | The class list with saved marks (this is the **review** screen) |
| `PUT /lecturer/course-offerings/{id}/marks` | `marks:enter` | Save draft marks. 200 |
| `POST /lecturer/course-offerings/{id}/marks/submit` | `marks:submit` | Submit: the marks lock. 200 |

My courses (`data: [ ... ]`), also the shape the Head's dashboard uses (section 8):

```json
{ "id": "01jd2k9m4q7x3v8n5r6t1y0zac",
  "course": { "code": "NUR 111", "title": "Foundations of Nursing", "credit_hours": 3 },
  "semester": { "id": "...", "academic_year": "2026/2027", "number": 1 },
  "classes": [ { "programme": { "code": "RGN", "name": "..." }, "year_of_study": 1 } ],
  "exam_date": "2026-12-14",
  "enrolled_count": 7, "marks_entered_count": 5,
  "status": "draft", "submitted_at": null, "released_at": null }
```

`status` is one of `not_started` (no marks saved), `draft` (some saved), `submitted` (locked), `released` (students can see it).

The class list and review, `GET .../marks`:

```json
{ "data": {
    "offering": { "id": "...", "course": { "code": "NUR 111", "title": "..." }, "status": "draft", "exam_date": "2026-12-14" },
    "assessment": { "id": "01jd...", "name": "Overall", "max_score": 100 },
    "grading": { "scale_name": "Demo scale (synthetic, not the College rules)", "is_demo": true },
    "students": [
      { "enrolment_id": "01jd...", "student": { "id": "01jd...", "index_number": "RGN/2025/0003", "name": "Kofi Owusu" },
        "score": 72.5, "letter_grade": "B", "state": "draft", "version": 1 },
      { "enrolment_id": "01jd...", "student": { "id": "...", "index_number": "RGN/2025/0004", "name": "Esi Darko" },
        "score": null, "letter_grade": null, "state": "empty", "version": null }
    ],
    "summary": { "enrolled": 7, "entered": 5, "missing": 2 }
} }
```

`letter_grade` is a **preview** computed from the active grade scale so the lecturer can review before submitting; it is stored for real only at submission. `state`: `empty`, `draft`, `submitted`. For a submitted course, `score` and `letter_grade` are the current approved version.

Save draft, `PUT .../marks`:

```json
{ "marks": [ { "enrolment_id": "01jd...", "score": 72.5 }, { "enrolment_id": "01jd...", "score": null } ] }
```

`score` is 0 to 100 with up to 2 decimals; `null` clears a draft mark. Only enrolments of this course are accepted. The whole request is validated first: if any row is wrong nothing is saved and the answer is 422 with row errors such as `errors["marks.1.score"]`. Answer 200: the same body as `GET .../marks`. After submission any `PUT` answers **423** `grade_locked`.

Submit, `POST .../marks/submit` (no body): every registered student must have a mark, otherwise 422 `marks_incomplete` with `errors.marks` listing the missing students. **(A6, Q5)** On success the marks lock (with no override for lecturers or tutors), the letter grades are stored using the active scale, and the answer is 200:

```json
{ "data": { "offering_id": "...", "status": "submitted", "submitted_at": "2026-12-16T10:05:00+00:00", "marks_count": 7 } }
```

Submitting again answers 409 `already_submitted` (or, with the same `Idempotency-Key`, replays the first answer). Not a lecturer of the course: 404. **As built:** a course with no registered students answers 422 `marks_incomplete` (nothing to submit); each missing student is a sentence in `errors.marks`, for example `RGN/2026/0003 (Yaw Boateng) has no mark.`; with no active grade scale the answer is 409 `no_grade_scale`. A `PUT` accepts at most 500 rows, each `enrolment_id` once. Clearing (`score: null`) deletes the draft row; a submitted mark can never be cleared (423). The database refuses a change to a submitted mark even if some future code tried (trigger), and the API turns that refusal into the same 423 `grade_locked`.

## 8. Head of Academics: dashboard and release

| Request | Permission | Notes |
|---|---|---|
| `GET /academics/course-offerings?status=&semester=&department=&page=` | `academics:dashboard:view` | Mark entry by course, same item shape as section 7 plus `lecturers: [{id, name}]`. `meta.summary`: `{not_started, draft, submitted, released}` |
| `GET /academics/course-offerings/{id}/results` | `academics:dashboard:view` | The submitted marks with scores, for review before release |
| `POST /academics/course-offerings/{id}/release` | `results:release` | Release the course's results to students. 200 |

**As built:** the dashboard defaults to the current semester (`?semester=` takes a semester id, `?department=` a department id, `?status=` one of the four statuses, `?per_page=` up to 100); `meta.summary` counts the courses in each status for that semester and department, ignoring the status filter. `academic_affairs` can read the dashboard and the submitted marks but cannot release; only `results:release` (head_of_academics and super_admin) can. `GET .../results` answers 409 `not_submitted` while the lecturer has not submitted, so the Head never sees drafts. `GET .../results` returns the section 7 class list shape (staff see `score`). `POST .../release` (no body): the course must be `submitted`, otherwise 409 `not_submitted`. On success `released_at` and the releaser are recorded, students can now see their grades, and the answer is 200 `{ data: { offering_id, status: "released", released_at } }`. Releasing a released course answers 200 with the same data (idempotent). **Release is per course. (A7, Q7)**

## 9. Student results

| Request | Permission | Notes |
|---|---|---|
| `GET /me/results` | `results:view-own` | My released results |

```json
{
  "data": {
    "student": { "index_number": "RGN/2025/0003", "programme": { "code": "RGN", "name": "..." }, "year_of_study": 2 },
    "semesters": [
      { "semester": { "id": "...", "academic_year": "2026/2027", "number": 1 },
        "courses": [ { "course_offering_id": "...", "code": "NUR 111", "title": "Foundations of Nursing", "credit_hours": 3, "letter_grade": "B", "released_at": "2026-12-18T09:00:00+00:00" } ],
        "credit_hours_total": 3 }
    ],
    "grading": { "scale_name": "Demo scale (synthetic, not the College rules)", "is_demo": true, "official": false }
  },
  "meta": { "note": "Letter grades and credit hours only. GPA is not computed by the API." }
}
```

Only released courses appear; a submitted but unreleased course does not. **As built:** `meta` carries only a `note`; `data.grading.official` is always `false`; courses are ordered by semester start date and then course code; a withdrawn registration never appears; an account with the permission but no student record gets 404. **No numeric marks, no percentages, no weighted average.** Your screen computes a sample GPA from `letter_grade` and `credit_hours` using the points in `GET /grade-scale` (below) and labels it not official. `semesters` is empty when nothing is released. Resit results are not part of Monday. **(A8, Q8, Q9)**

### Authorised correction (stretch, after the flow works)

`POST /results/{score_id}/corrections` with `{ "score": 74, "reason": "Marks were added up wrongly" }`, permission `marks:correct`. Creates a **new version** of the mark (the original is never changed), requires a reason, is audited, and updates what the student sees. `{score_id}` comes from `GET /academics/course-offerings/{id}/results`.

### The grade scale

`GET /grade-scale` (any signed-in active user) returns the active scale. For Monday it is **synthetic**: the letters, bands and points are placeholders, not the College's rules (D5), and `official` is always `false`.

```json
{ "data": {
    "name": "Demo scale (synthetic, not the College rules)",
    "is_demo": true, "official": false,
    "bands": [
      { "letter": "A", "min_score": 80, "max_score": 100, "points": 4.0 },
      { "letter": "B", "min_score": 70, "max_score": 79.99, "points": 3.0 },
      { "letter": "C", "min_score": 60, "max_score": 69.99, "points": 2.0 },
      { "letter": "D", "min_score": 50, "max_score": 59.99, "points": 1.0 },
      { "letter": "F", "min_score": 0, "max_score": 49.99, "points": 0.0 }
    ]
} }
```

Your screens calculate the sample GPA as the sum of `points` times `credit_hours` over the sum of `credit_hours`, using the `letter_grade` and `credit_hours` from `GET /me/results`, and label it "sample, not official". The API never computes or stores a GPA.

## 10. New error codes

| Status | `code` | When |
|---|---|---|
| 423 | `grade_locked` | A write to marks that are already submitted |
| 409 | `already_submitted` | Submitting a course twice |
| 409 | `not_submitted` | Releasing a course that is not submitted |
| 422 | `marks_incomplete` | Submitting while registered students have no mark |
| 422 | `registration_rejected` | One or more courses refused; `errors` explains each |
| 422 | `registration_closed` | No open window for the student |
| 422 | `idempotency_key_reused` | The same `Idempotency-Key` with a different body or on a different endpoint |
| 409 | `request_in_progress` | The same `Idempotency-Key` arrives while the first request is still running |

Everything else uses the existing codes (`validation_failed`, `forbidden`, `not_found`, ...).

## 11. Audit entries

Recorded automatically (readable with `GET /audit-logs` by `audit.view`): `student.registered`, `user.password_reset_by_admin` (built under this name; it applies to any account), `registration_window.created`, `registration_window.closed`, `enrolment.created`, `enrolment.withdrawn`, `marks.submitted`, `results.released`, `results.corrected` (with the reason). Draft saves are not audited one by one. Passwords and tokens are never recorded.

## 12. How your screens map (your field names to ours)

| Your screen and field | Ours |
|---|---|
| Course registration: `department`, `programme`, `year`, `semester`, `search` | query parameters `department`, `programme`, `year`, `semester`, `q` on `/me/course-offerings` and `/course-offerings` |
| Course registration: `selected`, `rejection`, `justRegistered` | `course_offering_ids` in the body; `errors` of a 422 `registration_rejected`; a 201 |
| Results and Transcript: `studentIndex`, `programme`, `year` | `data.student.index_number`, `.programme`, `.year_of_study` |
| Results: `semesters` | `data.semesters` |
| Transcript: `creditsCompleted` | `credit_hours_total` per semester (we do not decide what counts as completed) |
| `cumulativeGpa`, `gpaBySemester` | not returned; compute a sample on your side from `letter_grade`, `credit_hours` and `GET /grade-scale`, labelled not official |
| `cumulativeWa`, `waBySemester` (percentage averages) | not returned and never will be for students; remove those cards |
| Add an account: `name`, `email`, `programme`, `year`, `selectedCourses`; `createdPassword`, `createdIndex` | `POST /students` body; `meta.temporary_password` and `data.index_number` |
| Registration setup: `formAcademicYear`, `formSemester`, `formOpensAt`, `formClosesAt`, `formAudience` | `semester_id`, `opens_at`, `closes_at`, `audience` |
| Academics dashboard: Course, Lecturer, Class, Marks entered, Status, Exam date | `course`, `lecturers`, `classes`, `marks_entered_count` of `enrolled_count`, `status`, `exam_date` |
| `operationKey` | `Idempotency-Key` header |

## 13. Assumptions I made (please correct)

- **A1** A registration window is per semester with an optional audience of programme and year pairs; its status is computed.
- **A2** The index number is issued by the API at account creation, in a placeholder format.
- **A3** Course registration is all or nothing.
- **A4** Registration checks only: window open, course offered this semester, in the student's curriculum for their programme and year, not already registered. No credit limits, clashes, prerequisites or arrears gating.
- **A5** One mark per student per course (Overall, out of 100). No continuous assessment or exam components yet.
- **A6** A course cannot be submitted until every registered student has a mark.
- **A7** Release is per course offering, by the Head of Academics, and cannot be undone through the API.
- **A8** Students see letter grade and credit hours per released course, no GPA, no weighted average, no percentages.
- **A9** "Review" means the lecturer reviewing their own draft (the class list with letter-grade preview) before submitting. There is no separate approval step between submit and release.
- **A10** The letter grade comes from a synthetic demo scale, flagged `is_demo`, not the College's rules.
- **A11** (agreed 2026-11-21) The grade points in `GET /grade-scale` are a sample so your screens can show a GPA labelled not official; the API does not compute a GPA.

## 14. Questions for the frontend engineer

1. **(Q1) Who owns user accounts?** *Proposed and agreed in principle in your last message; please confirm the response shapes in section 1.* Our proposal: your login calls `POST /auth/login` and keeps the token in your server session, so our API is the only user store and your Fortify screens (passkeys, confirm-password) are not used for the demo. Does that work?
2. **(Q2) Permission names.** Are these final, including the new `results:release`, `registration:self` and `results:view-own`? Where does the Head's release live in your Roles and permissions screen?
3. **(Q3) Registration.** All or nothing (A3)? Which rejection reasons does your screen need to show?
4. **(Q4) Windows.** Is the `audience` as a list of programme and year pairs right (your `formAudience`)? Which statuses do you display?
5. **(Q5) Marks.** One total mark per course, or components with weights (your "app default weights")? Must every registered student have a mark before submit (A6)?
6. **(Q6) The Head screens.** Which fields does the release screen show? I have only seen the dashboard table.
7. **(Q7) Release** per course (A7), or per semester, programme or class?
8. **(Q8) Results.** Is letter grade plus credit hours per course enough for your Results and Transcript pages?
9. **(Q9) Resits.** How does your "Resit results" section expect data, and is it needed on Monday?
10. **(Q10) Account creation.** Is the single call in section 5 right for your "Add an account" screen? Do you need the API to suggest an email?
11. **(Q11) Paging.** `page` and `per_page` with `links` and `meta`: fine?
12. **(Q12) Student statuses.** Only `active` exists for now. Which others does your screens use (for the status-change requests)?
13. **(Q13) Idempotency.** Is sending a fresh `Idempotency-Key` per action, and reusing it on retry, acceptable?

## 15. What I need back, and when

Please send, ideally tonight or tomorrow morning so the contract can freeze on Saturday:

1. Your contract files: `docs/frontend/contracts`, `app/Contracts` and `app/Data` (the contents, or read access to those folders).
2. Answers to Q1 to Q13 above, or corrections written straight into this page.
3. Screenshots or a field list of the **lecturer mark entry** and the **Head of Academics release** screens.
4. Whether your side will call the API from the server (recommended) and the exact origin if the browser will.

## 16. What happens next

1. You correct this draft; I merge your changes and mark it frozen.
2. I build the endpoints in the order of sections 4 to 9, each with tests per role, audit entries, the OpenAPI entry and a Postman folder, and tell you as each is ready on the tunnel.
3. A joint test (script in `docs/frontend-integration.md`, extended with this flow) before Monday.
