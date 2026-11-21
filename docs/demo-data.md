# Synthetic demo data

Everything the demo shows is invented. **No real student, staff or applicant data is ever loaded** (Data Protection Act 2012, Act 843; the contract makes unauthorised disclosure uncapped liability). The seeders refuse to run outside the local environment.

## Load it

```powershell
php artisan migrate
php artisan db:seed                              # roles, demo accounts, and the academic data below
# or just the academic data (it also creates the roles and demo accounts it needs):
php artisan db:seed --class=AcademicDemoSeeder
```

It is safe to run again: nothing is duplicated. To start from nothing (this **deletes the local database content**, so only on a machine with no real data): `php artisan migrate:fresh --seed`.

## What you get

| What | How many | Notes |
|---|---|---|
| Departments | 3 | Nursing (NUR), Midwifery (MID), Student Development (SDV) |
| Programmes | 2 | Registered General Nursing (RGN), Registered Midwifery (RM), 3 years each |
| Academic year | 1 | 2026/2027 |
| Semesters | 2 | Each exactly 16 weeks. Semester 1 starts 2026-09-07 and is current; semester 2 starts 2027-01-18 |
| Cohorts | 6 | Intakes 2024, 2025, 2026 for each programme (year 3, 2 and 1) |
| Courses | 12 | Codes like `NUR 111`; credit hours are placeholders. Curriculum links give each course its programme, year and semester |
| Course offerings | 9 | The semester 1 courses, each with an exam date, a lecturer and one assessment called "Overall" |
| Lecturers | 8 | `lecturer@demo.test` and `lecturer.2` to `lecturer.8@demo.test`; `lecturer@demo.test` teaches at least two courses. `head_of_academics@demo.test` also gets a staff profile |
| Students | 41 | `student.001` to `student.040@demo.test` and `student@demo.test`. Names are randomly combined, never real people. Index numbers look like `RGN/2025/0003` (a placeholder format until the College confirms its own) |
| Registrations (enrolments) | about 66 | Each student is registered for their programme's courses for their year in semester 1 |
| Registration window | 1 | Open for semester 1 from two weeks ago to 30 days from now (so registering works whenever you load it) |
| Grade scale | 1 | "Demo scale (synthetic, not the College rules)": A 80-100, B 70-79.99, C 60-69.99, D 50-59.99, F 0-49.99, with sample points 4, 3, 2, 1, 0 so the frontend can show a GPA labelled "sample, not official" (ADR 0012). Marked `is_demo`; the API never computes a GPA |

All demo accounts use the password `password`, except `first-login@demo.test`, which must change it at first sign-in (see `docs/api-testing-postman.md`).

## Left empty on purpose

- **`student@demo.test` has nothing registered**, a first-year nursing student, so you can show course registration live.
- **No marks yet**, so the lecturer can enter, review and submit them live, the Head of Academics can release them, and the student can then see the results.
- Semester 2 has no offerings yet.

## What is not a College rule

The credit hours, dates, grade bands, course names and index number format are placeholders for demonstration. The College has not confirmed its grading scale, GPA metric, registration rules or index number format (D5 and the open points in `docs/erd.md`). Do not present any of it as official.

## For developers

- Seeder: `database/seeders/AcademicDemoSeeder.php`. Tests: `tests/Feature/Academics/AcademicDemoSeederTest.php`.
- Every row belongs to the default tenant (`docs/decisions/0010-multi-tenancy-built-in-switched-off.md`).
