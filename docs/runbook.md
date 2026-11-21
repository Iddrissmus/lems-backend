# Runbook

What to do when something breaks, and how to do the routine jobs. Written for the owner and whoever maintains the system after handover. Keep it open when you are on call.

**Status: draft.** It matches what is built today (2026-11-21). Parts that depend on a server that does not exist yet (backups, rolling back a release) say so. Read `docs/deployment.md` for how the server is built.

## 1. Quick reference

| Thing | Where |
|---|---|
| Is the app up? | `GET /api/v1/health` answers `{"status":"ok",...}` |
| Do the database and cache work? | `GET /api/v1/ready` answers `{"status":"ready","checks":{"database":"ok","cache":"ok"}}`; anything else is a 503 with the failing check |
| Application log | `storage/logs/laravel.log` |
| Mail (when `MAIL_MAILER=log`) | the same log file |
| Who did what | the audit log: `GET /api/v1/audit-logs` as `super_admin` |
| API reference | `docs/api/openapi.json`; the live page `/docs` exists only in local and staging |
| Settings | `.env` on the server (never in git) |
| What changed recently | `CHANGELOG.md` and `docs/PROJECT_LOG.md` |

Useful commands (run from the project folder):

```
php artisan about                    # versions and environment
php artisan migrate:status           # which migrations ran
php artisan schedule:list            # scheduled tasks
php artisan lems:create-super-admin  # create an administrator (asks for the password)
php artisan optimize:clear           # clear config, route and view caches
```

## 2. If the API is down or slow

Work down the list and stop at the first thing that explains it.

| Symptom | Likely cause | What to do |
|---|---|---|
| `/health` does not answer | Web server or PHP is down, or the server is off | Restart `nginx` and `php8.5-fpm`; check the server is running and the disk is not full (`df -h`) |
| `/health` is fine but `/ready` shows `database: error` | The database server is down or the credentials changed | Start the database service; check `DB_*` in `.env`; look at the database log |
| `/ready` shows `cache: error` | The cache table is missing or the database is unreachable | `php artisan migrate:status`; fix the database first |
| Everyone gets HTTP 500 | A bad release or a missing setting | Read the last lines of `storage/logs/laravel.log`; if it started with a release, roll back (section 8) |
| Everyone gets 401 | Tokens expired (12 hours) or `APP_KEY` changed | Sign in again; never change `APP_KEY` on a live system |
| Everyone shares one rate limit, or audit entries show the proxy's address | `TRUSTED_PROXIES` is not set for the proxy in front | Set it to the proxy's address and run `php artisan config:cache` |
| Screens work but feel slow | Server load, a slow query, or a tunnel (demo only) | Check `top`; check the log for slow queries; for the demo tunnel the extra second per call is normal |
| The disk is full | Logs or backups grew | Rotate or delete old logs; move backups off the server |
| 502 or 504 from Nginx | PHP-FPM is down or overloaded | `systemctl status php8.5-fpm`; restart it; look for a long-running request |

After any fix, run the smoke test: health and ready answer 200, sign in, `GET /me`, and read the audit log.

## 3. Account problems

| Problem | What to do |
|---|---|
| Someone forgot their password | They use "forgot password" on the portal (the link lasts 60 minutes and works once). If they cannot, an administrator issues a temporary password: `POST /users/{id}/reset-password` (the user must then choose their own, and is signed out everywhere) |
| An invited person's link expired (72 hours) | An administrator resends it: `POST /users/{id}/resend-invitation` |
| "Too many attempts" (HTTP 429) | Five wrong passwords for one email lock that email for 15 minutes. It clears by itself. Someone typing a colleague's email can lock them out; that is accepted (ADR 0007). In an emergency `php artisan cache:clear` clears all locks (the cache is the database; this is harmless) |
| A person left or is suspected of misuse | Disable the account: `POST /users/{id}/disable`. It signs them out everywhere at once. Enable again with `POST /users/{id}/enable` |
| The only `super_admin` is locked out or lost | `php artisan lems:create-super-admin` on the server creates another. The system refuses to remove or disable the last active super admin |
| A new student cannot sign in | The temporary password is shown **once** at registration. Issue a new one (`POST /users/{id}/reset-password`) |

An account can also be disabled in the portal by an administrator who holds `users.disable`.

## 4. Marks, release and results

| Situation | What it means | What to do |
|---|---|---|
| A lecturer says "it will not let me save" and the error is `423 grade_locked` | The marks were already **submitted**. They are locked on purpose, in the application and in the database | Nothing is broken. A correction is a new, audited version with a reason. **The correction endpoint is not built yet.** Record the request and escalate to the owner |
| The lecturer cannot submit: `422 marks_incomplete` | A registered student has no mark | The error lists who. Enter the marks, or ask whether the student should have withdrawn (withdrawing is only possible before marks exist and while a registration window is open) |
| The Head cannot release: `409 not_submitted` | The lecturer has not submitted | Ask the lecturer |
| A course was released by mistake | Release is **permanent through the API** | Do not edit the database by hand on your own. Tell the owner; any manual change must be written in `docs/PROJECT_LOG.md` with who approved it and why, and the audit log must carry the reason |
| A student says a result is wrong | The student sees only the letter grade; the lecturer and the Head see the score | The lecturer or the Head checks the score in the review screen. If it is wrong, it is a correction (see the first row) |
| A student cannot see a result | It is not released yet, or they withdrew from the course | The dashboard shows the course status (not started, draft, submitted, released) |

The grade scale (letters, bands and points) is a **demo placeholder**. Do not use it for real results until the College confirms its rules (decision D5).

## 5. If something looks like a security problem

Treat it as serious until shown otherwise. Do these first, in this order, and write down the time of each step.

1. **Contain.** Disable the account(s) involved. If the cause is unknown or widespread, revoke every sign-in: run `php artisan tinker --execute="DB::table('personal_access_tokens')->delete();"` (everyone must sign in again). If a secret leaked, change it (database password, mail credentials) and restart the application.
2. **Preserve evidence.** Do not delete logs. Copy `storage/logs/` and export the audit log (`GET /audit-logs`, page through it) to a safe place. Take a database backup now.
3. **Find out what happened.** The audit log records who did what, with the client address (for example: sign-ins, password resets, registrations, marks submitted, results released). It never records passwords or marks.
4. **Tell the owner immediately,** and follow the breach procedure in `docs/data-protection.md` section 7 (who decides, who is told, and in what time). Personal data of students is covered by the Data Protection Act, 2012 (Act 843).

## 6. Routine jobs

| When | Job |
|---|---|
| Daily | Look at the uptime alerts; skim `storage/logs/laravel.log` for new errors |
| Weekly | Check free disk space and the age of the newest backup |
| Monthly | **Restore test** (section 7): restore last night's backup into a scratch database and open it. Review the list of users and roles. Run `composer audit` |
| Each release | Follow `docs/deployment.md` section 7. Back up first |
| Each semester | Review the registration windows and the staff list; disable accounts of people who left |

## 7. Backups and restoring

**Requirements (contract, BE-093):** daily automated **off-site, encrypted** backups, a 24-hour recovery point (lose at most a day), a 4-hour recovery time (be running again within four hours), and a restore tested every month.

**Not built yet:** the automated schedule and the off-site storage (they depend on the hosting decision, D2). The procedure below is the manual version; automate it with cron once the server exists.

Take a backup (on the server; replace the names):

```
mysqldump --single-transaction --routines --triggers -u <backup user> -p <database> | gzip > lems-$(date +%F).sql.gz
```

The `--triggers` option matters: the grade lock is a trigger, and a backup without it would restore a database that no longer locks submitted marks. Encrypt the file before it leaves the server (for example `gpg --symmetric`), copy it off the server, and keep several days (retention is a decision for the College, see `docs/data-protection.md`).

Restore (always into a **scratch** database first):

1. Create an empty scratch database.
2. `gunzip -c lems-<date>.sql.gz | mysql -u <user> -p <scratch database>`.
3. Point a copy of the application at it (a separate `.env`), run `php artisan migrate:status` (should list every migration as run), sign in, and check the numbers you know (users, courses, released courses).
4. Check the lock still works: the triggers must exist (`SHOW TRIGGERS LIKE 'assessment_scores';` lists two).
5. Only when the scratch copy is right, and with the owner's approval, restore into the live database during a maintenance window.

Record every restore test (date, who, how long it took, what was checked) in `docs/PROJECT_LOG.md`; the time taken tells you whether the 4-hour recovery time is realistic.

## 8. Rolling back a release

- **No migration in the release:** check out the previous release, `composer install --no-dev --optimize-autoloader`, `php artisan config:cache && php artisan route:cache && php artisan event:cache`, then the smoke test.
- **The release has a migration:** do not run `migrate:rollback` on live data without thinking. Many migrations here create tables; rolling back would delete real records. Take a fresh backup, restore it into a scratch database, rehearse the rollback there, and do it live only with the owner's approval.
- A deployment script with instant rollback (release directories and a symlink swap) is **not built yet** (`docs/deployment.md`).

## 9. Demo and local environments

These do **not** apply to a live server. Never run them on staging or production.

| Job | Command |
|---|---|
| Rebuild the synthetic demo data from scratch (wipes the local database) | `php artisan migrate:fresh --seed` |
| Change the demo password | edit `DEMO_PASSWORD` in `.env`, then `php artisan db:seed` |
| Run the checks before a push | `composer ci` (Pint, Larastan, all tests) |
| Check a running API and its roles | `python scripts/demo-sweep.py <base url>` (needs `DEMO_PASSWORD` set) |

The full demo day procedure is `docs/demo.md`.

## 10. Who to call

Fill this in at handover; keep it out of git if it holds personal numbers.

| Role | Name | How to reach |
|---|---|---|
| System owner | | |
| Hosting provider support | | |
| College contact for outages and data incidents | | |
| Frontend maintainer | | |
