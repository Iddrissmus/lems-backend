# Deployment guide

How to build a server for the LEMS SCNMTC API from nothing, how to release a change, and how to undo one. Written for the owner and whoever maintains the server after handover.

**Status: draft.** Hosting (decision D2) is not decided and no staging or production server exists yet. The stack below is the brief's assumption (`setup.md` section 11) and the checklist is written so it can be followed on any Ubuntu server. Items marked **not built yet** are described but do not exist in the repository today. The Slice 0 demo does not use this page: it runs on the owner's laptop behind a tunnel (`docs/demo.md`).

## 1. What is decided and what is open

| Topic | State |
|---|---|
| Server | **Open (D2).** Assumed: an Ubuntu VPS the owner controls, Nginx with PHP-FPM, no Docker in production |
| PHP | 8.5. The code runs on 8.5.12 locally |
| Database engine | **Open (ADR 0006).** Development uses XAMPP's MariaDB 10.4. The brief says MySQL 8 with strict mode. Staging and production must run the **same engine as the tests**; decide before the first server is built and test the migrations on that engine |
| Redis | **Not used yet.** Cache, queue and sessions use the database. Add Redis only when a measured need appears |
| Queue workers | **Not needed today:** nothing is queued yet (the invitation email is sent in the request). When queued listeners arrive (notices, fee reminders), add Supervisor workers (section 6) |
| Scheduler | **Needed:** one scheduled task forgets idempotency keys older than 24 hours (`routes/console.php`). Run it with cron (section 5) |
| Multi-tenancy | Built in and **off** (`TENANCY_ENABLED=false`). Leave it off (ADR 0010) |
| TLS | Let's Encrypt, HTTPS only. The owner holds the domain and DNS |
| Backups | **Plan not built yet.** Requirements and the procedure to build are in `docs/runbook.md` |
| Sizing and price | **Open.** The contract promises a 4-hour recovery time and a fixed hosting price per student; size the server against both before choosing a plan (backlog notes on BE-093 and BE-096) |

## 2. Environments

| Environment | Purpose | Rules |
|---|---|---|
| Local | Development on the owner's laptop | `APP_ENV=local`. Demo seeders and the `/docs` page work here. Never holds real data |
| Staging | The owner's approval step before production | Own server or own virtual host, own database, own `.env`. `/docs` is available. Synthetic data only |
| Production | The College's live system | `APP_ENV=production`, `APP_DEBUG=false`. **The demo seeders refuse to run here** and `/docs` is closed. Never run `migrate:fresh`, `migrate:reset` or `db:wipe` |

Each environment has its own database and its own `.env`. No secret is ever committed: `.env` is ignored by git and `.env.example` lists every setting with a safe default.

## 3. Server requirements

- Ubuntu LTS, a non-root user with sudo, SSH key login only (password login off), a firewall allowing only 22, 80 and 443.
- Nginx and PHP 8.5-FPM with the extensions the project needs. After installing, run `composer check-platform-reqs` in the project; it lists anything missing.
- MySQL 8 or MariaDB (see section 1), with UTF-8 (`utf8mb4`).
- Composer 2, Git, and `unzip`.
- Let's Encrypt (`certbot`) for TLS.
- Cron (for the scheduler). Supervisor only once queued work exists.

**A database privilege to plan for.** The grade lock is a pair of database triggers (`assessment_scores_locked_update` and `_delete`). On MySQL with binary logging on (the default on MySQL 8), creating a trigger needs either the `SUPER` privilege for the user running migrations or the server setting `log_bin_trust_function_creators = 1`. If `php artisan migrate` fails on the results migration with a message about `SUPER` or binary logging, that is the cause. Use a separate migration user for deployments (below), not the application's everyday user.

## 4. Provisioning checklist

Follow in order. Tick each as you go.

1. **Server and access.** Create the server, add your SSH key, create a deploy user, disable password login and root login, enable the firewall (22, 80, 443), turn on automatic security updates.
2. **Packages.** Install Nginx, PHP 8.5-FPM and its extensions, the database server, Composer, Git, certbot. Run `composer check-platform-reqs` later to confirm.
3. **Database.** Create the database and two users:
   - a **migration user** with full rights on the application database (used only while deploying);
   - an **application user** with the everyday rights (`SELECT`, `INSERT`, `UPDATE`, `DELETE` on the application tables).
   - **Not built yet:** a stricter application user that may only `INSERT` and `SELECT` on `audit_logs`, with audit-log triggers that refuse updates and deletes, so even a compromised application cannot rewrite history. It is planned and tracked in `docs/PROJECT_LOG.md`; until it exists the audit log is protected only by the application.
4. **Code.** Clone the repository to `/var/www/lems-backend` (or the release-directory layout in section 7), checkout the release, run `composer install --no-dev --optimize-autoloader`.
5. **Settings.** Copy `.env.example` to `.env` and fill it in (section 8). Run `php artisan key:generate` **once** (never again on a live system: it would invalidate encrypted data such as saved idempotency responses).
6. **Web server.** Point Nginx at the project's `public` folder (sample below), reload Nginx, and get a certificate: `certbot --nginx -d <your domain>`. Confirm HTTP redirects to HTTPS.
7. **Permissions.** The web user must be able to write to `storage` and `bootstrap/cache` and nothing else.
8. **Database structure.** `php artisan migrate --force`, using the migration user's credentials. Then `php artisan db:seed --class=RolesAndPermissionsSeeder --force` to create the roles and permissions. **Do not** run `db:seed` without a class on production.
9. **First administrator.** `php artisan lems:create-super-admin` prompts for a name, an email and a password. Use a strong password held in a password manager. This is the only account created by hand; everyone else is invited.
10. **Caches.** `php artisan config:cache`, `php artisan route:cache`, `php artisan event:cache`.
11. **Scheduler.** Add the cron line in section 5.
12. **Monitoring.** Point an uptime check at `https://<domain>/api/v1/health` (the app is up) and a second at `https://<domain>/api/v1/ready` (the database and cache work). Alert on anything but HTTP 200. Add error tracking (Sentry or similar) when chosen; mask emails and phone numbers in it.
13. **Logs.** Laravel writes to `storage/logs/laravel.log`. Add log rotation (`logrotate`) so the disk does not fill. Set `LOG_LEVEL=warning` in production.
14. **Backups.** Set up the daily encrypted off-site backup and test one restore before go-live (`docs/runbook.md` section 7).
15. **Smoke test.** From another device: health and ready answer 200; sign in as the new super admin; `GET /api/v1/me` works; the audit log shows the sign-in.

### Sample Nginx site

```nginx
server {
    listen 80;
    server_name api.example.edu.gh;          # your domain
    root /var/www/lems-backend/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    client_max_body_size 5m;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`certbot --nginx` adds the HTTPS server block and the redirect. Never serve the project folder itself, only `public`.

## 5. The scheduler

One cron entry runs everything Laravel has scheduled (today: forgetting idempotency keys older than 24 hours):

```
* * * * * cd /var/www/lems-backend && php artisan schedule:run >> /dev/null 2>&1
```

Check it with `php artisan schedule:list`.

## 6. Queue workers (when queued work exists)

Nothing is queued today, so skip this until it is needed. When it is, run a worker under Supervisor so it restarts if it stops:

```ini
[program:lems-worker]
command=php /var/www/lems-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
```

After every deploy run `php artisan queue:restart` so workers load the new code.

## 7. Releasing a change

Release flow from the brief: work on a branch, `composer ci` passes, merge, deploy to **staging**, the owner approves, deploy to **production**.

**Not built yet:** `deploy.sh` with release directories and a symlink swap (instant rollback). Until it exists, release by hand, in this order, on staging first:

1. Tell the College the maintenance window if there will be an interruption (maintenance is meant to be announced and outside teaching hours).
2. Take a database backup (`docs/runbook.md` section 7). **Always, before migrating.**
3. `git fetch` and check out the release tag or the merged `main`.
4. `composer install --no-dev --optimize-autoloader`.
5. `php artisan migrate --force` (migration user). **A migration that deletes or rewrites data needs the owner's approval first.**
6. `php artisan config:cache && php artisan route:cache && php artisan event:cache`.
7. `php artisan queue:restart` (if workers exist).
8. Smoke test (checklist item 15) and watch `storage/logs/laravel.log` for ten minutes.

**Rolling back.** If the release is bad and has no migration: check out the previous release and repeat steps 4, 6 and 7. If it has a migration, do **not** run `migrate:rollback` blindly on live data; restore the backup taken in step 2 into a scratch database first, decide with the owner, and follow `docs/runbook.md` section 8.

## 8. Settings for staging and production

| Setting | Production value | Notes |
|---|---|---|
| `APP_ENV` | `production` | Staging: `staging` |
| `APP_DEBUG` | `false` | Always. `true` shows internals to anyone |
| `APP_KEY` | generated once | Lose it and saved encrypted data cannot be read |
| `APP_URL` | `https://<domain>` | |
| `DB_*` | the application user | Never the migration user |
| `FRONTEND_URL` | the portal's address | Emailed links point there |
| `FRONTEND_ORIGINS` | the portal's address | Only the sites that call the API from a browser |
| `TRUSTED_PROXIES` | the proxy's IP address(es), comma separated | Only if a proxy or load balancer sits in front. Do **not** use `*` in production |
| `SANCTUM_TOKEN_EXPIRATION` | `720` (minutes) | 12 hours; the College has not confirmed it |
| `TENANCY_ENABLED` | `false` | |
| `MAIL_*` | the College's mail provider | Not `log`: with `log`, emails (with sign-in links) go into the log file |
| `LOG_LEVEL` | `warning` | |
| `DEMO_PASSWORD` | **leave unset** | Only the demo seeders read it, and they refuse to run outside local |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` | Until Redis is added |

## 9. Security checklist before go-live

- [ ] HTTPS only; HTTP redirects; the certificate renews automatically (`certbot renew --dry-run`).
- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] `/docs` is refused in production (it is only served in local and staging); check it from outside.
- [ ] No demo accounts exist (no `@demo.test` users). `GET /api/v1/users` shows only real staff.
- [ ] The first super admin has a strong, unique password; the account list is reviewed.
- [ ] SSH password login is off; the firewall allows 22, 80 and 443 only.
- [ ] The database is reachable only from the server itself.
- [ ] Backups run and one restore has been tested.
- [ ] `composer audit` shows no known vulnerable dependency (it found none on 2026-11-21; run it again before go-live).
- [ ] Rate limits and lockout are in place (sign-in: 20 requests a minute per address, 5 failures lock an email for 15 minutes).
- [ ] The data-protection items in `docs/data-protection.md` are decided and written down.

## 10. Still to do (tracked in `docs/PROJECT_LOG.md`)

- Choose hosting and the database engine; build staging; test the migrations on that engine.
- `deploy.sh` (release directories, symlink swap, rollback).
- The restricted application database user with audit-log triggers.
- Backups and the monthly restore test (`docs/runbook.md`).
- Error tracking and structured logs; the uptime checks above.
- Redis, if load or queue needs ask for it.
