# Data protection

What personal data the system holds, who can see it, what protects it today, and the decisions the College still has to make. Written for the owner and for the College's representative, to be completed together before go-live.

**Status: draft for the owner and the College to review.** This page records facts about the software as it is built (2026-11-21). It is **not legal advice**. The legal and policy decisions are marked **DECIDE** and are not made: this document does not invent them. The law that applies is the Data Protection Act, 2012 (Act 843) of Ghana; the contract treats a breach as the subject of uncapped liability (clause 12.4), and the backlog item is BE-092. Have a qualified person confirm the legal points.

## 1. The one rule that holds today

**Only synthetic data is allowed in development, testing and the demo.** No real student, staff or applicant data is entered into the laptop, the demo, an AI tool, a chat, a screenshot or an issue tracker. The demo accounts end in `@demo.test` and the people in the demo data are invented (`docs/demo-data.md`). Real data enters the system only on a production server built to `docs/deployment.md`, after the decisions in section 6 are made.

## 2. What personal data the system holds

| Data | Where | About whom | Why it is held |
|---|---|---|---|
| Name, email, password (as a one-way hash), account status, last sign-in time | `users` | Everyone with an account | To sign in and to show who they are |
| Index number, programme, cohort, year of study, status, number of repeats, the Ministry of Health membership number (optional, stored for cross-reference only, never used as an identifier), photo path (not used yet) | `student_profiles` | Students | The academic record |
| Staff number, department | `staff_profiles` | Staff | To assign lecturers to courses |
| Registrations for courses and their status | `enrolments` | Students | To know who studies what |
| **Marks** (numeric score, letter grade, who entered and submitted them, when), including earlier versions if a mark is ever corrected | `assessment_scores` | Students | The results |
| Who did what, when, from which network address and browser, with before and after facts of the change (never passwords, tokens or marks) | `audit_logs` | Everyone who uses the system | Accountability, and finding out what happened after an incident |
| Sign-in tokens (hashed) with name, last-used time and expiry | `personal_access_tokens` | Everyone who signs in | To keep people signed in for 12 hours |
| Invitation and password-reset tokens (hashed), with the email and expiry | `invitations`, `password_reset_tokens` | People being invited or resetting | One-time links |
| The saved answers to recent writes, **encrypted**, kept 24 hours, then forgotten | `idempotency_keys` | Whoever made the request | So a retried request is not done twice (and the one-time temporary password is not lost) |
| Application logs | `storage/logs` | Whoever causes an error | Finding faults. **Rule: no personal data in logs** (see section 4) |

Not held yet (so not covered here): fees and payments, announcements, uploaded documents, applications, clinical placements and library records. Each needs its own review when it is built.

## 3. Who can see what

Access is decided in the API on every request, never only in the screens.

| Person | Can see |
|---|---|
| A student | Their own record, their own registrations, and **only the letter grade and credit hours** of results the Head of Academics has released. Never a numeric mark, a percentage or a weighted average, and never anyone else's data |
| A lecturer | The students and marks of **the courses they are assigned to** (another lecturer's course looks like it does not exist) |
| Head of Academics, academic affairs | Courses, marks (scores) of submitted courses, and student lists. Only the Head can release results |
| Registrar, ICT administrator | Student records and the ability to create accounts and issue temporary passwords. They do not see marks |
| Super admin | Everything, including the audit log |
| Bursar | Nothing yet (fees are not built) |

Roles and what each may do are in `docs/api/demo-contract.md` section 2 and `docs/decisions/0005-roles-and-permissions.md`. The Head and the ICT administrator hold the same account-management rights (the owner's decision). **DECIDE (College):** whether each role's access is right for the real staff, before go-live.

## 4. What protects the data today

Built and tested:

- **Sign-in:** no public sign-up (accounts are invited or created by an administrator). Passwords are stored only as one-way hashes. Five wrong passwords lock an email for 15 minutes; the sign-in endpoints are limited to 20 requests a minute per address. Tokens expire after 12 hours and are revoked on sign-out, on a password reset (all of them), on a password change (the other devices) and when an account is disabled.
- **Temporary passwords** (for accounts created for someone else) are generated by the system, shown **once**, never stored readably, never written to the audit log, and the person must choose their own before doing anything else.
- **Access control:** permissions per role, checked on every request; students are limited to their own records; lecturers to their own courses.
- **Marks are locked when submitted,** by the application and by the database itself, so nobody can quietly change a published result. A correction is a new version with a reason, recorded in the audit log.
- **Audit log:** records sign-ins, account and role changes, registrations, marks submitted and results released. It never contains passwords, tokens or marks.
- **Encryption of the idempotency answers** at rest (they can contain a one-time password). Everything else relies on the database and disk; see the gap below.
- **No personal data in error answers:** with `APP_DEBUG=false` the API shows no internals. The error format never includes a stack trace.
- **Tenant isolation** is built in but switched off (ADR 0010); it matters only if a second college is added.
- **Dependencies** are checked with `composer audit` (no known advisories on 2026-11-21).

Gaps to close before real data (tracked in `docs/PROJECT_LOG.md`):

- **Encryption at rest of the database and its backups** (BE-091) depends on the server chosen (disk encryption, or encrypted backups). Not built.
- **Backups** are not automated yet (`docs/runbook.md` section 7).
- **The audit log is protected only by the application.** A restricted database user and triggers that refuse updates and deletes of audit rows are planned, not built.
- **Logs:** a sign-in link or temporary password can reach the log if the mail driver is `log`. Use a real mail driver in production and keep `LOG_LEVEL=warning`. Confirm by reading the log that no names, emails or marks appear.
- **Reading and exporting data about one person** is not built (section 5).
- **Two-factor sign-in** is not built; sign-in is password only.
- **Sign-in over HTTPS only** applies once the production server has its certificate (`docs/deployment.md`).

## 5. What a person can ask for

The Act gives people rights over their data (to know what is held, to have errors corrected, and so on). The system today can answer these only by hand:

| Request | What exists |
|---|---|
| "What do you hold about me?" | The tables in section 2. An export for one person is **not built**; the owner would assemble it from the database |
| "Correct my details" | An administrator can update a user (name, email, status). Student fields have no edit endpoint yet |
| "Correct my mark" | A correction (a new, audited version) is **not built** yet; see `docs/runbook.md` section 4 |
| "Delete my data" | Not built, and **not simply possible**: academic records may have to be kept (see retention below). **DECIDE** |

**DECIDE (College and owner):** who receives such a request, who checks the person's identity, who produces the answer, and within what time. Write the process down and name the person.

## 6. Decisions the College must make (BE-092)

None of these is decided. Record each answer here, with the date and who decided, and as an ADR in `docs/decisions/` if it changes the software.

| # | Decision | Notes | Answer |
|---|---|---|---|
| 1 | **The lawful basis** for processing student and staff data | Typically the College's duty to run its programmes and keep academic records; consent where it applies (for example photos). Have it confirmed | |
| 2 | **Who is the data controller** and who is the contact person | The College is expected to be the controller and the software provider a processor; the contract's roles and clause 12.4 decide. Check whether the College must register with the Data Protection Commission | |
| 3 | **How long records are kept** after a student leaves or an account is deactivated (FR-2.6) | The system keeps everything today and never deletes a result. Academic results are usually kept very long; logs and audit entries need their own period; backups need a rotation period | |
| 4 | **How requests from a person are served** (section 5) | | |
| 5 | **The breach procedure** (section 7) | Who decides, who is told, how fast | |
| 6 | **Where the data may be stored** (hosting country and provider) | Decision D2. Check whether hosting abroad is allowed for this data | |
| 7 | **Sharing with others** | The Ministry of Health membership number, external assessors and clinical sites arrive in later modules and each needs its own rule (the backlog treats the clinical scoping as the highest risk) | |
| 8 | **Minimum personal data** | Confirm that every field in section 2 is needed. Remove the ones that are not | |

## 7. Breach procedure (draft for the owner and College to confirm)

A breach is anything that exposes personal data to someone who should not have it, or loses or damages it: a leaked password, a wrong person seeing marks, a stolen laptop, a database copy sent to the wrong place, or an attacker.

**First 24 hours.** The contract asks for a written plan of who does what in the first 24 hours. Suggested shape; confirm each step and fill in the names:

| When | Who | What |
|---|---|---|
| Straight away | Whoever notices | Tell the system owner. Do not delete anything. Write down what you saw and when |
| Within the hour | Owner | **Contain:** disable the accounts involved, revoke all sign-ins if needed, change leaked secrets (`docs/runbook.md` section 5). **Preserve:** copy the logs and export the audit log; take a database backup |
| Within a few hours | Owner | **Assess:** what data, how many people, since when, what was seen or taken, is it still happening. The audit log shows who did what from where |
| Within 24 hours | Owner, then the College's responsible person | **Decide and report:** the College decides whether the Data Protection Commission and the affected people must be told, and how. **DECIDE the legal deadline and the form with a qualified person**; this document does not state it |
| Afterwards | Owner | Fix the cause, write what happened and what changed in `docs/PROJECT_LOG.md`, review whether a rule or a test should be added. An insurer will ask for this record |

Name the people here when decided:

| Role | Name and contact |
|---|---|
| System owner | |
| The College's responsible person | |
| Who talks to the Commission and the affected people | |

## 8. Rules for everyone who works on the system

- Never use real personal data outside production. Use the synthetic demo data (`php artisan migrate:fresh --seed` on a laptop).
- Never paste real data into a chat, an AI tool, an issue, a screenshot or an email. Examples in documents use invented people.
- Never commit secrets: `.env`, passwords, tokens, keys. `.env` is ignored by git; check `git status` before every commit.
- Keep the demo password out of the repository; it lives in `.env`.
- Do not edit data in the database by hand. If you must, write down who approved it and why in `docs/PROJECT_LOG.md`.
- Use production access only when you need it, and only with an account that is yours.
- Report anything odd straight away (section 7).

## 9. Review

Review this page at every release that adds personal data (fees, announcements, uploads, applications, clinical placements) and at least once a year. Update `docs/handover-checklist.md` when a section is completed.
