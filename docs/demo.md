# Demo guide: Slice 0, Monday 23 November 2026

How to run the Slice 0 demo: the checklist for the days before, the ten-minute script, what to say, and what to do when something goes wrong. Written for the backend owner, who runs the API from the laptop, and for whoever presents with the frontend. The demo shows the portal (built by the frontend team) talking to this API, with synthetic data only.

## 1. What the demo shows, and what it does not

One student goes through the whole flow:

1. The **registrar** registers a student. The API issues an index number and a temporary password, shown once.
2. The **student** signs in, is made to choose their own password, and registers for courses while the registration window is open.
3. The **lecturer** enters marks for the class, reviews them with a letter-grade preview, and submits. Submitting **locks** the marks: nobody can change them afterwards, not even the lecturer.
4. The **Head of Academics** sees the course as submitted, reviews the scores and **releases** the results.
5. The **student** sees a **letter grade and credit hours only**.

Extras if time allows: the ICT administrator issues a new temporary password; the super admin reads the audit trail.

Be honest about what is placeholder. Say these out loud if asked, and never present them as the College's rules:

- The grade bands (A 80 and above and so on), the credit hours, the course list and the index number format are **invented for the demo**. The College has not confirmed them.
- The GPA on the student's screen is a **sample worked out by the frontend** from sample points and is labelled "not official". The API never calculates a GPA and never sends a percentage or a mark to a student.
- All people and marks are **synthetic**. No real student or staff data is on the laptop or in the demo.
- Sign-in is password only. Passkeys and two-factor sign-in are not part of the demo.

## 2. Before the day

Do these on Saturday or Sunday, then once more on Monday morning.

| # | Check | How |
|---|---|---|
| 1 | The branches are merged into `main` and you are on it | GitHub: pull requests for `phase-1/registration` (done) and `phase-1/marks`; then `git checkout main` and `git pull` |
| 2 | `.env` is ready for a demo that others can reach | See the table in section 3 |
| 3 | The demo password is yours, not the default | `DEMO_PASSWORD` in `.env`, at least 8 characters. Give it only to the people who need it |
| 4 | The database is clean and seeded | `php artisan migrate:fresh --seed` (see the warning below) |
| 5 | The API answers | `php artisan serve`, then open `http://localhost:8000/api/v1/ready`: `{"status":"ready",...}` |
| 6 | Every demo account signs in | Postman, folder 1, or the frontend's own sign-in |
| 7 | The whole flow works end to end, on a **scratch** database | Postman folder 9 and folder 13 (`docs/api-testing-postman.md`). Run them against a scratch database (`lems_backend_test`), **not** the one you will present with, because folder 13 submits marks for good |
| 8 | The frontend can sign in through the tunnel and run the flow | A joint test with the frontend engineer: section 4 |
| 9 | A fallback exists | Postman open with the environment set to the tunnel, signed in as each role; the flow in section 5 can be shown straight from the API if the portal fails |

**Warning about `php artisan migrate:fresh --seed`.** It deletes everything in the database named in `.env` and rebuilds the synthetic demo data. That is what you want before a demo. It is only for your own laptop. If you created a real `super_admin` with `php artisan lems:create-super-admin`, it is deleted too; create it again afterwards.

**After the last rehearsal, reset once more** with `php artisan migrate:fresh --seed` and do not run any Postman folder against that database before presenting. Registration and marks leave data behind, and submitted marks cannot be undone.

## 3. Settings in `.env` for the day

| Setting | Value | Why |
|---|---|---|
| `APP_ENV` | `local` | The demo accounts and the `/docs` page only exist in local and staging |
| `APP_DEBUG` | `false` | With `true`, an error shows internal details to anyone with the link |
| `APP_URL` | the tunnel address | Links the API generates |
| `TRUSTED_PROXIES` | `*` | Behind a tunnel the API must trust the forwarded headers, or every user looks like one person (shared rate limit, wrong IP in the audit log) |
| `FRONTEND_URL` and `FRONTEND_ORIGINS` | `https://thescnmtc-lms.fly.dev` | Emailed links point to the portal; the origin is only needed if the browser calls the API directly |
| `MAIL_MAILER` | `log` | No real email is sent; emails are written to `storage/logs/laravel.log` |
| `DEMO_PASSWORD` | your own value | The password of every demo account. After changing it run `php artisan db:seed` |

After changing `.env`, restart `php artisan serve`. The full wiring guide, including the frontend's side, is `docs/frontend-integration.md`.

## 4. Start of the day, 30 minutes before

Start in this order and leave each running:

1. **XAMPP MySQL** (the green light, and `http://localhost:8000/api/v1/ready` later shows `database: ok`).
2. **The API:** `php artisan serve` in terminal 1.
3. **The tunnel:** `ngrok http 8000` in terminal 2. It prints an address such as `https://abcd-1234.ngrok-free.app`. The address **changes every time ngrok restarts**.
4. Put that address in `APP_URL`, restart `php artisan serve`, and open `https://<address>/api/v1/health` from your phone on mobile data. Expect `{"status":"ok",...}`.
5. **Send the frontend engineer** the base URL (`https://<address>/api/v1`). His server sets it as `LEMS_API_URL` and sends the header `ngrok-skip-browser-warning: 1` while ngrok is in front.
6. Sign in once in the portal as each role to prove the connection. Do not register, submit or release anything yet.
7. Keep the laptop plugged in, awake and online (turn off sleep for the day). Close anything that uses the port 8000 or 3306.

Anyone who has the tunnel address can call the API, and every demo account has the same password. That is acceptable only because every record is synthetic. Do not post the address publicly, and stop ngrok as soon as the demo is over.

## 5. The ten-minute script

Accounts (all use the demo password): `registrar@demo.test`, `lecturer@demo.test`, `head_of_academics@demo.test`, `ict_admin@demo.test`, `super_admin@demo.test`. The student is created live in step 1.

### Step 1: The registrar registers a student (about 1.5 minutes)

Sign in as `registrar@demo.test`. Open the screen for adding an account.

- Name: for example `Efua Demo`; programme **RGN**; the 2026 cohort (year 1). Leave the first courses empty; the student registers them in step 2.
- **Say:** "One action creates the sign-in account, the student record and the index number."
- The screen shows the **index number** (the format is a placeholder, for example `RGN/2026/0042`) and a **temporary password, once**. Copy both.
- **Say:** "The password is shown once and is never stored readably or written to the audit log. The student must change it at first sign-in."

### Step 2: The student signs in and registers for courses (about 2 minutes)

Sign out; sign in as the new student with the temporary password.

- The portal sends them to **choose a new password** (the API refuses everything else until they do). Choose one.
- Open course registration. The registration window is open for their programme and year. The list shows only courses for their programme and year, so the student cannot pick a year 2 course.
- Select **NUR 111 Foundations of Nursing** and **SDV 101 Study Skills**, and register. They appear as registered.
- **Say:** "Registration is all or nothing. If one course were refused, none would be registered, and each refusal would come with a reason." Optional: try registering NUR 111 again to show the reason "already registered".

### Step 3: The lecturer enters, reviews and submits marks (about 3 minutes)

Sign in as `lecturer@demo.test`. The lecturer teaches **NUR 111** (8 students now: 7 seeded plus the new one) and **SDV 101**.

- Open NUR 111. The class list shows every registered student with an empty mark.
- Enter marks for all but the last student, for example: 75, 92, 63, 55, 41, 88, 70. Save. The review shows a **letter grade preview** next to each mark (the first one is a B).
- Try to **submit**. The API **refuses** and names the student with no mark.
- Enter the last student's mark, `78` (a B), save, and **submit**. The course now shows as submitted.
- Try to change a mark. It is **refused: the marks are locked**.
- **Say:** "Submitting locks the marks. That is enforced twice: by the application and by the database itself, so no later code, tutor or lecturer can quietly change a submitted mark. A correction would be a new, audited version with a reason."

### Step 4: The Head of Academics reviews and releases (about 1.5 minutes)

Sign in as `head_of_academics@demo.test`.

- Open the academics dashboard. NUR 111 shows as **submitted**, with its lecturer and 8 of 8 marks. The counts by status are at the top.
- Open the submitted marks: the head sees the scores and letter grades.
- **Release** the results.
- **Say:** "Submitting alone publishes nothing. Only the Head of Academics' release makes the results visible, per course."

### Step 5: The student sees the result (about 1 minute)

Sign in as the new student again, open results.

- NUR 111 shows a **letter grade and credit hours**. SDV 101 is not shown: the lecturer has not submitted it and it has not been released.
- Point at what is **not** there: no marks, no percentages, no weighted average.
- **Say:** "The GPA card is a sample, worked out by the portal from sample points the API provides, and it says so on screen."

### Extras (if time allows, about 1 minute)

- Sign in as `ict_admin@demo.test`, find the new student and **issue a new temporary password**. The student is signed out everywhere.
- Sign in as `super_admin@demo.test` and open the **audit log**: the student registered, the registrations, the marks submitted, the results released, the password reset. No passwords and no marks appear in it.

## 6. If something goes wrong

| What you see | Likely cause | What to do |
|---|---|---|
| The portal shows an HTML page or a parse error where data should be | ngrok's browser warning page | Make the frontend's server send `ngrok-skip-browser-warning: 1` |
| Everything fails at once | The tunnel address changed (ngrok restarted) or the laptop slept | Restart ngrok, update `APP_URL`, send the frontend engineer the new address, restart `php artisan serve` |
| `ready` shows `database: error` | MySQL is not running | Start MySQL in XAMPP, then retry |
| 401 on every call | The token expired (12 hours) or the portal lost its session | Sign in again |
| 403 `password_change_required` | The account still has a temporary password | The portal must send the user to change it (expected for the new student in step 2) |
| 403 `forbidden` | Wrong account for that screen | Check the role; the lecturer cannot release, the head cannot enter marks |
| 422 `marks_incomplete` | A student has no mark yet | Expected in step 3: it names who is missing |
| 423 `grade_locked` | The marks are already submitted | Expected in step 3. Not a fault |
| 409 `already_submitted` or `not_submitted` | The course is in a different state than the screen assumed | Reload the dashboard; follow the order lecturer, head, student |
| 429 | Too many sign-in attempts in a minute | Wait the seconds in the `Retry-After` header |
| A student cannot be found or the class is the wrong size | A rehearsal left data behind | Stop, then `php artisan migrate:fresh --seed` and start again |
| The portal itself is broken but the API is fine | Frontend problem | Show the same flow from Postman (folder 10, 11 and 12 by role) |

Never debug live with `APP_DEBUG=true` while the tunnel is open: an error page would show internals to anyone with the link.

## 7. After the demo

1. Stop `ngrok` and `php artisan serve`.
2. Set `APP_DEBUG` back to what you use locally and clear `TRUSTED_PROXIES`.
3. Change `DEMO_PASSWORD` (or stop using the demo accounts) so the value that was shared stops working: edit `.env`, then `php artisan db:seed`.
4. Write down what the frontend engineer and the audience asked for, in `docs/PROJECT_LOG.md`, so the next slice starts from it.
