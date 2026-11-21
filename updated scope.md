# CLAUDE.md — SCNMTC Platform Backend

> STATUS: DRAFT v0.3, awaiting owner (Iddriss) approval. Replaces v0.2. Scope is now taken from "SCNMTC Platform — Backend Product Backlog" (CIS L'Urano Technologies, 9 Nov 2026).
> Items marked **[ASSUMPTION]** or listed in section 3 must be confirmed with the owner before they are built.

## 0. Read this first

1. **What this is:** the backend API for the SCNMTC college portal (Savior Church Nursing & Midwifery Training College, Osiem, Ghana). The frontend is a **separate repo owned by another team**. We never edit it. We agree an API contract with them.
2. **Hard deadline: a working demo on Monday 23 Nov 2026.** The scope of that demo is "Slice 0" in section 7. Everything else in the backlog comes after, in phase order.
3. **Existing work:** the owner has already started something. **Step 0 is to audit the existing code** (structure, `composer.json`, migrations, routes, tests, `.env.example`) and report back. Adapt to it where sensible. Never overwrite or delete existing work without the owner's explicit approval.
4. **Real student data never touches development.** Use synthetic seed data only (section 2).
5. **The owner wants oversight of everything.** Follow the working agreement in section 13: plan first, wait for approval, small commits.

## 1. Context

- **Product:** LEMS SCNMTC, "Records, results and fees, in one place". Students see results, fees and notices. Staff manage academics, results, fees, communications, clinical placements, stores and reporting.
- **Frontend (separate team/repo):** a Laravel app currently at https://thescnmtc-lms.fly.dev with `/login`, `/register`, `/forgot-password` and a public marketing page. We have **not seen the screens behind login**, so the real payload shapes are unknown (D3).
- **Backlog traceability:** every item is `BE-nnn` and traces to a Schedule 1 requirement (`FR` functional, `NFR` non-functional, `DM` data migration). `FE-nnn` is the frontend counterpart in the "Pair" column. Neither side is done until its pair is done.
- **Priority:** Must / Should / Could, carried from Schedule 1. A **Must item blocks phase acceptance**. Should/Could slipping does not. Build Musts first within a phase.
- **Scope:** 119 requirements are in scope (10 excluded). **NEW-2** (practical examination assessment with department dashboard) is excluded and must NOT be built without a Change Request. **BE-001 (multi-tenancy)** has no requirement reference. It is included deliberately because the Supplier plans to resell the platform: build it now, leave it switched off.
- **Phases and weeks (Schedule 3, contractual):** 1A weeks 1–4, 1B weeks 5–8, 2 weeks 9–12, 3 weeks 13–17. Periods run from the later of the Effective Date and receipt of the first instalment.
- **Live scale:** 514 current students, up to 14 clinical sites.
- Numbering gaps (BE-046, 051, 056, 076, 084) and the `b` suffix items (036b, 037b) are in the source; leave them as is.

## 2. Non-negotiables: security and data

The platform holds personal data covered by the **Data Protection Act 2012 (Act 843)**, and the contract makes unauthorised disclosure of student data **uncapped liability**. Treat these as hard rules:

- **Synthetic data only** in dev, test, demo and staging (Faker + seeders). No real student, staff or applicant data in the repo, logs, screenshots, issues, prompts or AI tools. Real data only ever lives on approved production infrastructure (D2).
- **Authorization enforced at the data layer**, not just controllers or the frontend: Policies on every endpoint **plus** global query scopes (tenant, role, assigned-student scope). "The frontend is never the only filter."
- **Least privilege:** students see only their own data. Lecturers see only their assigned courses. External assessors see only assigned students for the rotation window.
- **No PII in logs, error trackers or exception messages.** Mask emails and phones.
- **Secrets** only in environment variables. `.env` is never committed. Keep `.env.example` current.
- **TLS everywhere; encryption at rest** for personal data and credentials (BE-091). Passwords with Argon2id/bcrypt. Rate limit login and sensitive endpoints.
- **Breach procedure** (24-hour notice) is documented before go-live (BE-092).

## 3. Open decisions (propose, wait for the owner; do not pick silently)

| # | Decision | Default if owner says "your call" |
|---|---|---|
| D1 | Frontend auth style | **Bearer tokens (Laravel Sanctum personal access tokens).** Works for both a server-to-server Laravel frontend and a browser app. Cookie-SPA auth needs a shared parent domain, which two separate `*.fly.dev` apps do not have. |
| D2 | Where the backend is hosted ("Supplier's infrastructure", BE-096): provider, OS, SSH, panel | **[ASSUMPTION]** Ubuntu VPS, Nginx + PHP-FPM + MySQL. **Demo fallback:** run locally and expose via a tunnel (Cloudflare Tunnel or ngrok) with synthetic data only. |
| D3 | Real frontend payload shapes and screens | Use the draft contract in section 6. Send it to the frontend team on day 1 and adopt their corrections. |
| D4 | Source of migration data (Excel workbooks, templates) and who supplies them | None until the College supplies them. Phase 1A/1B. |
| D5 | College's confirmed **GPA/CGPA metric**, grade scale, pass marks, resit rules | **Do not invent.** Build the engine as configuration (grade-scale table + strategy class). Real values from the College. |
| D6 | SMS provider (BE-034). Email first, SMS fallback | Interface + log driver. |
| D7 | Repo host | **[ASSUMPTION]** GitHub (the backlog mentions GitHub issues). |
| D8 | Payment gateway provider (BE-063/064). The College opens the merchant account in its own name | Adapter interface + sandbox/fake driver. Manual recording and bank reconciliation come first. |
| D9 | MFA method for privileged staff (BE-003) | TOTP. |
| D10 | Full list of user groups in Schedule 1 (BE-002) | Roles in section 5.3. Confirm against Schedule 1. |
| D11 | Retention periods and grace period for deactivated students (FR-2.5/2.6) | Configurable, no hard-coded values. |
| D12 | Minimum response threshold for anonymous evaluations (BE-054). Agreed with Academic Affairs | Config value `EVALUATION_MIN_RESPONSES`. |
| D13 | Meaning of BI requirement FR-12.1 (fixed dashboards or self-service querying) | Fixed aggregate endpoints only until the College clarifies. |
| D14 | Wording of FR-5.2/5.3 on whether lecturer identity is hidden from the student or from Academic Affairs (BE-048) | Ask before building. |
| D15 | Concrete capacity ceiling (BE-095) and support hours/response times (BE-098) | Ask. Record in `docs/decisions/`. |

## 4. Stack and tooling

- PHP 8.3+, latest stable Laravel (check at scaffold time and pin). **API-only**, no Blade UI.
- **MySQL 8.x** (InnoDB, utf8mb4, strict mode on in all environments). ULIDs for public IDs.
- Auth: Laravel Sanctum (bearer tokens). Roles/permissions: `spatie/laravel-permission`.
- Queues: `database` driver for now (Redis optional later). Scheduler via cron.
- Tests: Pest. Static analysis: Larastan (level 6+). Style: Laravel Pint.
- API docs: `dedoc/scramble` (OpenAPI generated from code) served at `/docs`.
- Files: private local disk behind Laravel's filesystem abstraction (swappable to S3-compatible later). Never public by default. Serve via signed URLs.
- PDFs (receipts, transcripts, letters): `barryvdh/laravel-dompdf` or similar. Excel import/export: `maatwebsite/excel`.
- **Dev environment:** owner is on Windows (HP laptop). Default to **WSL2 (Ubuntu) + Docker Desktop with Laravel Sail** so commands match the Linux production target. Document it in `README.md`. Do not assume Linux-only shell tricks without a WSL path.

## 5. Architecture rules

### 5.1 Layering
- Versioned JSON API under `/api/v1`.
- Controller (thin) → **Form Request** (validation) → **Action/Service** (business logic) → Eloquent model → **API Resource** (output). No raw models in responses.
- Organise by domain: `app/Domain/{Identity,Academics,Results,Fees,Communication,Admissions,Clinical,Stores,Reporting,Audit}/...` with Actions, DTOs, Enums, Events, Policies.
- PHP backed Enums for statuses. Events + queued Listeners for side effects (email, SMS, notifications).
- Enable `Model::preventLazyLoading()` outside production. Index every foreign key and common filter.

### 5.2 Multi-tenancy (BE-001) — build now, switched off
- A `tenants` table, a seeded default tenant, and a `tenant_id` column on **every** domain table (non-null, FK, part of composite unique indexes).
- A `BelongsToTenant` trait with a **global scope** and automatic `tenant_id` assignment on create, driven by a `TenantContext` resolved from the authenticated user.
- `TENANCY_ENABLED=false` means a single default tenant, but the column and scope stay in place.
- Tests prove a record in tenant A is invisible to tenant B for representative models (users, students, results).

### 5.3 Identity and roles (BE-002, BE-003, BE-005, BE-006)
- Accounts are **created by the College (admin/bulk), never self-registered**. The API does not expose public registration (FR-1.7, DM-6). Existing student emails get credentials issued with **forced password change at first login**.
- Roles **[ASSUMPTION, confirm with Schedule 1]:** `super_admin`, `ict_admin`, `head_of_academics`, `academic_affairs`, `registrar`, `bursar`, `lecturer`, `student`, `clinical_coordinator`, `external_assessor`, `stores_officer`, `librarian`, `src_officer`.
- Permission vocabulary (e.g. `results.submit`, `results.correct`, `fees.record_payment`) is the contract the frontend consumes via `/me`. Settle it in week 1.
- Authentication: unique credentials, password policy, session/token timeout, **MFA (TOTP) for privileged staff** (phase 1A, after Slice 0).
- Password reset: emailed, expiring, single-use token. Rate limited.

### 5.4 Audit log (BE-007, BE-026)
- **Append-only, immutable.** Enforce at the data layer: the application DB user has INSERT/SELECT only on `audit_logs`, plus `BEFORE UPDATE/DELETE` triggers that raise an error (defence in depth).
- Every privileged and record-changing action writes who, what, before/after, why (where required), IP, user agent, timestamp. Grade changes carry who, when and **why**.

### 5.5 Results and grade locking (BE-022 → BE-032)
- Marks have `draft` and `submitted` states. **On submit the record locks. All later writes are rejected, with no lecturer or tutor override.**
- Enforce locking **at the data layer, not in a controller**: immutable score rows (a BEFORE UPDATE trigger rejecting changes to locked rows) and corrections modelled as **new versions** created through an authorised Academic Affairs route (BE-025). The current grade is the latest approved version. Every correction is audited.
- Error on a write to a locked record: HTTP **423** with `code: "grade_locked"`.
- Results release to students shows **letter grades only; percentages are withheld** (BE-027). Enforce in the Resource and Policy, not the frontend.
- GPA/CGPA engine (BE-028/029) uses the College's confirmed metric (D5), **excludes superseded attempts**, handles resits (replacement grade used, both attempts retained, credit hours increased) and keeps repeated years preserved outside GPA (BE-032).
- **Build the reconciliation harness first** (BE-028 + BE-036 are one piece of work): load the College's Excel workbooks, compute with the new engine, diff every student. A mismatch found before publishing is a bug. After publishing to 514 students it is a credibility problem.
- Transcripts carry a **NON-OFFICIAL watermark** (BE-030).

### 5.6 Money (Phase 2)
- Stored as **integer pesewas** (`*_pesewas`), currency `GHS`. Never floats.
- Payments are append-only. Reversals are counter-entries. Idempotency keys on every payment-recording and gateway-callback endpoint.
- `PaymentGateway` interface with a `FakeGateway`/sandbox driver now. The real provider is swapped in when the College's merchant account exists (D8). Webhook verification and idempotent settlement are shared with the frontend (section 10).
- Arrears rules gate registration, result release and transcript download (BE-065).
- Semester start date is a **first-class, auditable field**: the semester is exactly 16 weeks, and billing reads from the calendar (BE-019).

### 5.7 Other rules
- Dates stored in UTC. App timezone `Africa/Accra` for display logic.
- Soft deletes on business records. Hard deletes only by explicit decision.
- Wrap money, result-publishing, status-transition and role changes in DB transactions.
- Student status changes go through a **state machine** with reason and date on every transition (BE-011). Second repeat triggers dismissal (BE-012).
- Index numbers: abbreviation + programme + year + serial, **collision-safe** (row-locked sequence table). The Ministry of Health membership ID is stored for cross-reference only, **never as a key** (BE-008/009).
- Evaluation aggregates cannot be de-anonymised: return no aggregate below the minimum response threshold (D12). The anonymous complaints channel keeps **no reverse linkage** to the author (BE-050).
- External assessor access (BE-079, the highest-risk item): scope is a **global query constraint** that every clinical read inherits, time-limited to the rotation, with **automatic revocation on rotation end**. **Write the test that proves an officer at site A cannot read a student at site B before writing the feature.**

## 6. Frontend integration contract (draft v0 for the fly.io app)

The frontend is owned by another team and its expectations are unknown. This is our **proposal**. Send it on day 1 and adopt their corrections. Breaking changes inside `/v1` need agreement with them. Generate OpenAPI via Scramble and share `/docs`.

**How the frontend connects (confirm with them):**
- *Option A, server-to-server:* the Laravel frontend calls this API from its server using the bearer token.
- *Option B, browser:* the frontend's JS calls the API directly (needs CORS).
- Both use the same bearer-token API. CORS allowed origins come from env `FRONTEND_ORIGINS` (comma-separated). Staging default: `https://thescnmtc-lms.fly.dev`. Never use `*`.

**Conventions**
- Base: `https://<api-host>/api/v1`. Headers: `Accept: application/json`, `Authorization: Bearer <token>`.
- Success: `{ "data": ..., "meta": {...} }`. Lists are paginated: `meta.current_page, per_page, total` plus `links`. Max `per_page` 100.
- Errors (RFC 7807 style): `{ "message": "...", "code": "snake_case_code", "errors": { "field": ["..."] } }`. Status codes: 401 unauthenticated, 403 forbidden, 404, 409 conflict, **423 locked**, 422 validation, 429 rate limited.
- Money in pesewas (integers) with `currency: "GHS"`. Dates ISO-8601 UTC.
- Idempotent writes accept `Idempotency-Key` header.

**Slice 0 endpoints (draft)**

```
POST /auth/login            {email, password, device_name} ->
  { data: { token, user: {id, name, email, roles[], permissions[], must_change_password} } }
POST /auth/logout
GET  /me                    -> same user object + profile (student or staff)
POST /auth/password/forgot  {email}
POST /auth/password/reset   {email, token, password, password_confirmation}
POST /auth/password/change  {current_password, password, password_confirmation}

GET  /me/results            letter grades only, per semester, with GPA/CGPA
GET  /me/fees               invoices, payments, balance
GET  /announcements         targeted to the caller
GET  /health                {status, version, time}   (no auth)

GET  /lecturer/courses                      assigned courses this semester
GET  /lecturer/courses/{id}/marks           draft/submitted state per student
PUT  /lecturer/courses/{id}/marks           save draft marks
POST /lecturer/courses/{id}/marks/submit    submit -> locks (later writes 423 grade_locked)
POST /results/{id}/corrections              academic_affairs only; reason required; new version + audit
POST /payments                              bursar records a receipt (Idempotency-Key)
POST /announcements                         admin creates, targeted by programme/year/cohort/role
GET  /audit-logs                            admin only; filter by actor, entity, date
```

## 7. Slice 0 — Monday 23 Nov 2026 demo

**Goal:** a solid, demonstrable first slice that proves the architecture and is ready for the frontend team to connect to. It is not feature-complete and must not pretend to be.

**Demo must have**
1. Running at a **public HTTPS URL** (staging, or a tunnel as fallback, D2), with synthetic data only.
2. **Interactive API docs at `/docs`** (Scramble), plus a Postman/Bruno collection with the demo flow.
3. Auth end-to-end with seeded users per role (student, lecturer, head_of_academics, academic_affairs, bursar, ict_admin), including forced password change on first login and password reset (logged mail driver is fine).
4. Student read endpoints (`/me`, `/me/results`, `/me/fees`, `/announcements`).
5. Lecturer mark entry → submit → **lock**, with the 423 `grade_locked` response on a later write, and an Academic Affairs correction that creates a new version with a reason.
6. Bursar records a receipt and the student balance updates.
7. Admin creates a targeted announcement. Only the intended audience sees it.
8. **Audit log** showing the above actions, append-only (demonstrate that an UPDATE/DELETE is rejected).
9. CORS configured for `https://thescnmtc-lms.fly.dev` via `FRONTEND_ORIGINS`, with a short `docs/frontend-integration.md` for the frontend team (base URL, auth flow, example calls, error format).
10. Multi-tenancy columns and scope in place (switched off). `composer ci` passes. `docs/demo.md` contains a 10-minute demo script.

**Seed data (synthetic):** one default tenant, 2 programmes (Nursing, Midwifery), 3 departments, ~40 students across years/cohorts, ~8 lecturers, ~12 courses, 2 semesters (16 weeks each), results with letter grades, a fee schedule with invoices and part-payments, a handful of announcements.

**Explicitly NOT in the demo:** MFA, payment gateway, bulk import, data migration, evaluations, contestations, clinical placements, stores, BI, e-library. If shown, say they are planned.

**Suggested schedule** (owner reviews each day's plan before work starts)
- **Fri:** Step 0 audit, scaffold/adapt, Sail on WSL2, `composer ci` + pre-push hook, auth + Sanctum, roles/permissions, multi-tenancy trait, audit log + DB protections, health endpoint, send contract draft to frontend team.
- **Sat:** academic model (programmes, departments, courses, calendar, cohorts, students), mark capture, locking + corrections, seeders.
- **Sun:** fees (schedule, invoices, payments, balances), announcements, `/docs`, CORS, tunnel/staging deploy, Postman collection.
- **Mon morning:** rehearse `docs/demo.md`, fix rough edges, freeze. Do not start new features.

**If time runs short:** cut in this order: announcements targeting, corrections route, fees. Keep auth, roles, results-locking and audit: they are the core of the architecture.

## 8. Backlog

Source of truth for scope. `FE` is the paired frontend item. Phase guidance from the backlog is noted under each table.

### Phase 1A — Foundation (weeks 1–4)

| ID | Item | Pri | Req | FE |
|---|---|---|---|---|
| BE-001 | Multi-tenancy: tenant column on every table and a globally scoped query layer | Must | none | — |
| BE-002 | Role and permission model covering every user group named in Schedule 1 | Must | FR-9.4, FR-10.1, FR-10.6 | FE-017 |
| BE-003 | Authentication: unique credentials, password policy, session timeout, MFA for privileged staff | Must | NFR-6 | FE-002 |
| BE-004 | Environments and deployment pipeline: live and test, migrations, seeding, rollback | Must | NFR-12 | FE-001 |
| BE-005 | Password reset issue and verification | Must | FR-1.8 | FE-003 |
| BE-006 | Credential issue against existing student emails with forced change at first login | Must | DM-6 | FE-004 |
| BE-007 | Immutable audit log, append-only, covering privileged and record-changing actions | Must | NFR-9, FR-4.13 | FE-024 |
| BE-008 | Index number generation: abbreviation, programme, year, serial, collision-safe | Must | FR-1.5 | FE-009 |
| BE-009 | Ministry of Health membership ID stored for cross-reference only, never as a key | Must | FR-1.6 | FE-009 |
| BE-010 | Student record model and query API: profile, programme, year, department, photograph | Must | FR-2.1 | FE-005 |
| BE-011 | Student status state machine with reason and date on every transition | Should | FR-2.7, FR-2.3 | FE-007 |
| BE-012 | Repeat counting, with the second repeat triggering dismissal | Must | FR-2.2 | FE-008 |
| BE-013 | Bulk student import: template parsing, validation, partial-failure reporting | Must | FR-2.8, DM-1 | FE-010 |
| BE-014 | Retention of academic records for deactivated students, and portal deactivation after the grace period | Must | FR-2.5, FR-2.6 | FE-007 |
| BE-015 | Department and programme model, extensible without redevelopment | Must | FR-3.2, FR-3.3 | FE-011 |
| BE-016 | Course and subject model with credit values, versioned across curriculum review | Must | FR-3.4 | FE-012 |
| BE-017 | Lecturer-to-course assignment per semester | Must | FR-3.5 | FE-013 |
| BE-018 | Cohort membership and year-end progression | Must | FR-3.6 | FE-014 |
| BE-019 | Academic calendar: years, two 16-week semesters, examination windows, rotation blocks | Must | FR-3.1, FR-3.8 | FE-015 |
| BE-020 | Academic advisor and board head associations | Should | FR-3.7 | FE-016 |
| BE-021 | Migration of staff and lecturer records with course assignments, plus cleansing and de-duplication | Must | DM-4, DM-7 | FE-010 |

### Phase 1B — Grading and results (weeks 5–8)

| ID | Item | Pri | Req | FE |
|---|---|---|---|---|
| BE-022 | Mark capture API for a lecturer's assigned courses, with draft and submitted states | Must | FR-4.1 | FE-018 |
| BE-023 | Grade locking: lock on submit, reject all later writes, no lecturer or tutor override | Must | FR-4.2, FR-6.5 | FE-019 |
| BE-024 | Bulk result upload and download against the defined template, with row-level validation | Should | FR-4.11 | FE-021 |
| BE-025 | Authorised correction route through Academic Affairs, with the locking trigger defined | Must | FR-4.3 | FE-023 |
| BE-026 | Grade change audit entries carrying who, when and why, immutable | Must | FR-4.13 | FE-024 |
| BE-027 | Results release to the student portal only, with letter grades exposed and percentages withheld | Must | FR-4.4, FR-4.5 | FE-025 |
| BE-028 | GPA and CGPA engine on the College's confirmed metric, excluding superseded attempts | Must | FR-4.6, FR-4.7 | FE-026 |
| BE-029 | Resit handling: replacement grade in the calculation, both attempts retained, credit hours increased | Must | FR-4.8 | FE-027 |
| BE-030 | Transcript generation with the NON-OFFICIAL watermark | Must | FR-4.9 | FE-028 |
| BE-031 | Graduation list | Could | FR-4.12 | FE-029 |
| BE-032 | Historical record of repeated years preserved outside the GPA calculation | Must | FR-2.4 | FE-026 |
| BE-033 | Announcement model with audience targeting by department, programme, year and cohort | Must | FR-11.1, FR-11.3 | FE-030 |
| BE-034 | Notification dispatch: email delivery, trigger events, delivery status, SMS fallback | Must | FR-11.2, FR-11.6, FR-11.7 | FE-032 |
| BE-035 | SRC announcement channel with its approval step | Should | FR-11.4 | FE-067 |
| BE-036 | Migration of historical results and the data needed to compute CGPA for continuing students | Must | DM-2, DM-3 | FE-010 |
| BE-037 | Parallel run of old and new grading for one period, against an agreed freeze date | Should | DM-8 | — |

### Phase 2 — Academic and financial operations (weeks 9–12)

Only phase with third-party integrations. The payment and SMS gateways sit on accounts the College opens in its own name, so build against sandboxes behind interfaces until they exist.

| ID | Item | Pri | Req | FE |
|---|---|---|---|---|
| BE-036b | Applicant record model: personal details, WASSCE/SSSCE results, photograph reference | Should | FR-1.9 | FE-033 |
| BE-037b | Ministry of Health applicant list ingestion and validation | Must | FR-1.2 | FE-034 |
| BE-038 | Admission decision recording and the notification trigger | Should | FR-1.4 | FE-035 |
| BE-039 | First-year enrolment, repeatable each cycle with no reconfiguration | Must | FR-1.1, FR-1.10 | FE-036 |
| BE-040 | Portal account creation for the current student body at launch | Must | FR-1.7 | FE-010 |
| BE-041 | Assignment model with deadlines and publication state | Must | FR-6.1 | FE-037 |
| BE-042 | Submission handling: file storage, size and type limits, server-side timestamp | Must | FR-6.2 | FE-038 |
| BE-043 | Marking and feedback, feeding continuous assessment | Must | FR-6.3 | FE-039 |
| BE-044 | Project assessment | Should | FR-6.4 | FE-040 |
| BE-045 | Conferencing integration for virtual classes | Should | FR-6.6 | FE-041 |
| BE-047 | Contestation model with the window and committed response time | Must | FR-5.1, FR-5.6 | FE-042 |
| BE-048 | Contestation routing to Academic Affairs, with lecturer identity withheld at the data layer | Must | FR-5.2, FR-5.3 | FE-043 |
| BE-049 | Contestation status tracking and outcome notification | Should | FR-5.5 | FE-044 |
| BE-050 | Anonymous complaints channel with no reverse linkage to the author | Should | FR-5.4 | FE-045 |
| BE-052 | Evaluation model and window control tied to revision week and the examination calendar | Must | FR-7.1, FR-7.3 | FE-046 |
| BE-053 | Configurable evaluation forms and scoring criteria | Should | FR-7.5 | FE-047 |
| BE-054 | Evaluation aggregation that cannot be de-anonymised, including at low response counts | Must | FR-7.2 | FE-048 |
| BE-055 | Evaluation aggregates per lecturer, course and department | Should | FR-7.6 | FE-049 |
| BE-057 | Fee schedule model per programme, year and semester | Must | FR-9.2 | FE-050 |
| BE-058 | Receipt posting against a student record, including bank payments quoting the student identifier | Must | FR-9.1 | FE-051 |
| BE-059 | Part-payments and instalment splits against a fee item | Must | FR-9.5 | FE-052 |
| BE-060 | Bank payment reconciliation against student accounts | Must | FR-9.6 | FE-053 |
| BE-061 | Student balance and payment history | Must | FR-9.3 | FE-054 |
| BE-062 | Receipt document generation | Should | FR-9.7 | FE-055 |
| BE-063 | Payment gateway integration, settling to the College's own merchant account | Must | FR-9.10 | FE-056 |
| BE-064 | Commitment fee notification and payment capture against the applicant | Must | FR-9.11 | FE-057 |
| BE-065 | Arrears rules gating registration, result release and transcript download | Must | FR-9.9 | FE-058 |
| BE-066 | Migration of outstanding fee balances as at the cutover date | Must | DM-5 | FE-010 |
| BE-067 | Reporting and BI query layer behind the role-based dashboards | Must | FR-12.1 | FE-060 |
| BE-068 | Student performance aggregates by cohort, programme, department, course and lecturer | Must | FR-12.2 | FE-061 |
| BE-069 | Enrolment and admissions aggregates per intake cycle | Should | FR-12.3 | FE-062 |
| BE-070 | Fee collection and arrears aggregates | Should | FR-9.8, FR-12.4 | FE-063 |
| BE-071 | Pass, fail, repeat and dismissal rates per cohort and course | Should | FR-12.6 | FE-064 |
| BE-072 | Report export to Excel and PDF | Should | FR-12.7 | FE-065 |
| BE-073 | Statutory returns for the Nursing and Midwifery Council and Ministry of Health | Must | FR-12.8 | FE-066 |
| BE-074 | Official letter generation: warning, repeat, dismissal | Should | FR-11.5 | FE-068 |
| BE-075 | News and announcement portal backend | Should | FR-11.9 | FE-069 |

Notes: BE-063/064 go behind an adapter interface from the start (a sandbox swap, not a rewrite, when the College's account exists). BE-054 needs a minimum-response threshold agreed with Academic Affairs (D12). **BE-067 cannot be sized until D13 is answered.**

### Phase 3 — Clinical and operational (weeks 13–17)

Opens the platform to people outside the College: up to 14 clinical sites get accounts against a database holding all students' records.

| ID | Item | Pri | Req | FE |
|---|---|---|---|---|
| BE-077 | Placement site register with the named clinical coordinator per site | Must | FR-8.1 | FE-071 |
| BE-078 | Rotation model: student, site, block, supervisor | Should | FR-8.6 | FE-072 |
| BE-079 | External assessor access, scoped to assigned students, time-limited to the rotation, revocable | Must | FR-8.2, FR-8.7 | FE-073 |
| BE-080 | Standardized clinical assessment model on the College's own format | Must | FR-8.3 | FE-074 |
| BE-081 | Idempotent assessment submission, safe against dropout and replay | Should | FR-8.8, NFR-3 | FE-075 |
| BE-082 | Attendance, hours and procedure logging on placement | Should | FR-8.9 | FE-076 |
| BE-083 | Clinical placement and rotation aggregates | Should | FR-12.5 | FE-077 |
| BE-085 | Stores inventory model, including practical consumables | Should | FR-10.2 | FE-079 |
| BE-086 | Stores issue against a practical session, department or student group | Should | FR-10.3 | FE-080 |
| BE-087 | Purchase requests and their approval route | Could | FR-10.4 | FE-081 |
| BE-088 | Stock levels and reorder alerting | Could | FR-10.5 | FE-082 |
| BE-089 | Announcement history, read and delivery tracking, automatic archiving after a configurable period | Could | FR-11.8 | FE-083 |
| BE-090 | E-library backend, content model to be settled with the College's librarian | Should | NEW-1 | FE-085 |

Note: BE-090 is unestimated until the librarian is consulted. Schedule that conversation during Phase 2 so the item is specified or deferred by Change Request before week 13.

### Cross-cutting (whole platform)

| ID | Item | Pri | Req |
|---|---|---|---|
| BE-091 | Encryption: TLS in transit, encryption at rest for personal data and credentials | Must | NFR-8 |
| BE-092 | Data protection compliance under Act 843: lawful basis, retention, subject access, breach procedure | Must | NFR-7 |
| BE-093 | Backup and recovery: daily automated off-site backups, 24-hour RPO, 4-hour RTO, restores tested monthly | Must | NFR-10 |
| BE-094 | Availability: 99.5% monthly uptime, maintenance announced and outside teaching hours | Should | NFR-1 |
| BE-095 | Capacity: sized for the current body plus ten years of growth and further departments | Should | NFR-11 |
| BE-096 | Hosting: provisioning of live and test environments on the Supplier's infrastructure | Should | NFR-12 |
| BE-097 | API performance budget supporting a 3-second page on a 3G connection | Should | NFR-2 |
| BE-098 | Support process: hours, response times and escalation route as set in the contract | Must | NFR-14 |
| BE-099 | Technical handover documentation and the administrator guide | Must | NFR-15 |

Notes: BE-092 contains decisions, not just code: lawful basis, retention after deactivation, how a subject access request is served, who does what in the first 24 hours of a suspected breach. Write them in `docs/data-protection.md` before go-live. BE-093: the 4-hour RTO and off-site encrypted backups must be sized against the hosting budget early. If they cost more than allowed, raise it, don't absorb it. BE-095 needs a concrete ceiling (students, concurrent users, storage), not "ten years of growth".

## 9. Shared decisions with the frontend team

These need agreement before either side builds. **Settle the first four in week 1.**

| Decision | Backend | Frontend | What the backend owns |
|---|---|---|---|
| Role model | BE-002 | FE-017, 059, 070, 078 | The permission vocabulary every screen consumes |
| Grade locking | BE-023 | FE-019, 020 | Data-layer enforcement, the lock trigger, the error on a locked write |
| Idempotent submission | BE-081 | FE-087 (the phase table pairs FE-075; clarify) | Request key, deduplication window, replay safety |
| Access scoping | BE-079 | FE-073 | The global query constraint; the frontend is never the only filter |
| GPA and CGPA rules | BE-028, 029 | FE-026, 027 | The calculation itself and the payload shape for repeats and resits |
| Bulk upload | BE-013, 024 | FE-010, 021 | Template parsing, validation error shape, partial-failure semantics |
| Report export | BE-072 | FE-065 | Server-side Excel/PDF rendering, pagination limits |
| Payment callback | BE-063, 064 | FE-056, 057 | Webhook verification, idempotent settlement, the pending state |
| Notification triggers | BE-034 | FE-032, 084 | Event catalogue, channel fallback, exposed delivery status |

## 10. Testing and quality gates

- **No hosted CI for now.** One `composer ci` script runs Pint, Larastan, Pest and a migration on a clean test DB. A git **pre-push hook** blocks pushes until it passes. Add a CI runner later if the repo host allows.
- Pest feature tests for every endpoint: happy path, validation, authorization **per role**, tenant isolation.
- Unit tests for GPA/CGPA, resit logic, state machine, index-number generation, money handling.
- **Security tests first** for BE-079 (site A cannot read site B), BE-023 (locked write returns 423), BE-054 (below-threshold aggregates are never returned), audit immutability.
- Factories + seeders produce realistic **synthetic** data.
- The reconciliation harness for BE-028/036 is a test suite in its own right.

## 11. Deployment and operations

- Environments: **local → test/staging → live**, each with its own database and `.env`. Deploy target is a server the Supplier controls (D2). No dependency on Fly.io for the backend.
- Release flow: branch → `composer ci` green → merge → staging → owner approval → live. Use a versioned `deploy.sh` (or Deployer) with release directories and symlink swap so rollback is instant: pull, `composer install --no-dev -o`, `php artisan migrate --force`, cache config/routes, restart queue workers. Destructive migrations need owner approval.
- Server: Nginx, PHP-FPM, MySQL, Supervisor (queue workers), cron (scheduler), TLS via Let's Encrypt, firewall, SSH keys only.
- Health endpoint, structured logs without PII, error tracking, uptime monitoring (the frontend host returned a 502 during discovery).
- Daily automated encrypted **off-site** backups; 24-hour RPO, 4-hour RTO; restore tested monthly (BE-093).
- `docs/deployment.md` includes a full server provisioning checklist so the server can be rebuilt from scratch.

## 12. Documentation (owner is responsible; Claude drafts)

`README.md` (WSL2/Sail setup), `docs/architecture.md`, `docs/erd.md` (Mermaid, kept current), OpenAPI at `/docs`, `docs/frontend-integration.md`, `docs/demo.md`, `docs/deployment.md`, `docs/runbook.md`, `docs/data-protection.md`, `docs/decisions/` (ADRs), `CHANGELOG.md`, and the administrator guide (BE-099).

## 13. Working agreement

1. **Plan before code.** For each slice or module, post a short plan (files, migrations, endpoints, tests) and wait for approval.
2. **Step 0:** audit the existing code first. Report what exists and what you would keep, change or discard. Wait for approval.
3. Small, reviewable commits on branches, one concern each. Never commit to `main` directly. Never commit secrets or real data.
4. **Never** run destructive commands (`migrate:fresh`, dropping tables, deleting files) except on local/test data, and say so when you do.
5. **Do not invent business rules** (grading metric, pass marks, fee rules, thresholds, retention). Ask, record the answer in `docs/decisions/`, then build.
6. Do not build excluded items (NEW-2) or anything without a BE ID. Flag it instead.
7. When the frontend contract is unclear, stop and ask. Do not guess shapes silently. Record assumptions in the OpenAPI docs.
8. Keep `.env.example`, docs and OpenAPI in sync with code in the same change.
9. Explain choices briefly so the owner can follow and learn.
10. Prioritise **Must** items. Raise any item that cannot be sized or built because of a dependency (D-list) rather than absorbing it.

## 14. Definition of done (per backlog item)

Migration + model + policy + request + action + resource + route; authorization tested per role and tenant; audit logging where applicable; tests passing; OpenAPI updated; seeders updated (synthetic); paired frontend item (FE) agreed; owner has reviewed.

## 15. Immediate next steps

1. Owner approves this brief and answers what they can of D1–D15 (priority: D1, D2, D3, D5).
2. **Step 0 audit** of the existing code. Report back.
3. Send the section 6 contract to the frontend team and ask for their expected payload shapes.
4. Execute the Slice 0 schedule in section 7.
