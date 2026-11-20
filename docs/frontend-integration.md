# Connecting the frontend to the API

How to connect the frontend app (https://thescnmtc-lms.fly.dev, built by another team) to this backend, for the Monday demo and after. Written for two readers: the backend owner, who prepares the API side, and the frontend engineer, who prepares theirs. We have not seen the frontend's code, so everything about their side is a **proposal** to confirm with them.

For the exact request and response shapes read `docs/api/frontend-guide.md` and `docs/api/openapi.json`. This page is about wiring the two apps together.

## 1. The picture

```
 Student / staff browser
          |  HTTPS
          v
 Frontend app (Laravel, fly.dev)            <-- the frontend team's code
          |  HTTPS, JSON, "Authorization: Bearer <token>"
          v
 Tunnel (ngrok)  ->  this API on the owner's laptop  ->  database
```

**Recommended: the frontend's Laravel server calls the API** (server to server). Their server holds the token in its own session; the browser never sees it. No CORS is involved, and it works between two different domains.

Alternative: the browser's JavaScript calls the API directly. Then the token lives in the browser and the API must list the frontend's origin (`FRONTEND_ORIGINS`). Possible, but it is less safe and needs more setup, so we use it only if the frontend team prefers.

> Confirm with the frontend engineer: server-side or browser calls?

## 2. Backend side: prepare the API (the owner)

### Before the day

1. The database is running (XAMPP > MySQL), and the data is in place:

   ```powershell
   php artisan migrate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   php artisan db:seed --class=DemoUsersSeeder
   ```
2. `docs/api-testing-postman.md` Part 3 passes (35 of 35).
3. The settings in `.env` for a demo that others can reach:

   | Setting | Value | Why |
   |---|---|---|
   | `APP_ENV` | `local` | The demo accounts and the `/docs` page only exist in local and staging |
   | `APP_DEBUG` | `false` | **Important.** With `true`, an error shows internal details to anyone with the link |
   | `APP_URL` | the tunnel address (step below) | Links the API generates |
   | `FRONTEND_URL` | `https://thescnmtc-lms.fly.dev` | Emailed links point to the frontend's pages |
   | `FRONTEND_ORIGINS` | `https://thescnmtc-lms.fly.dev` | Only needed if the browser calls the API directly |
   | `TRUSTED_PROXIES` | `*` | Behind a tunnel the API must trust the forwarded headers; otherwise every user looks like one person (shared rate limit) and the audit log records the wrong IP |
   | `MAIL_MAILER` | `log` | No real emails are sent; they are written to `storage/logs/laravel.log` |

   After changing `.env`, restart the server.

### Start it and open the tunnel

```powershell
php artisan serve          # terminal 1: leave it running
ngrok http 8000            # terminal 2: leave it running
```

ngrok prints an address such as `https://abcd-1234.ngrok-free.app`. That is your **public API address**; the base URL for the frontend is that address plus `/api/v1`. Put the address in `APP_URL` and restart `php artisan serve`.

Check it from any device: open `https://<tunnel>/api/v1/health`. **Expect** `{"status":"ok","version":"...","time":"..."}`.

Things to know about the tunnel:
- The address **changes every time ngrok restarts** (on the free plan). Send the frontend engineer the new one each time.
- The laptop must stay awake, online, and running MySQL, `php artisan serve` and ngrok for the whole demo.
- If the frontend gets an HTML page instead of JSON, it is ngrok's browser warning page: have their server send the header `ngrok-skip-browser-warning: 1`.
- Anyone with the address can call the API. This is acceptable **only because the data is synthetic**. Never put real student, staff or applicant data on it (Act 843, and the contract's uncapped liability).
- The demo accounts all use the password `password`. On a public address I recommend giving them stronger passwords first (ask the developer to add a `DEMO_PASSWORD` setting) so a stranger who finds the address cannot sign in as `super_admin`.

### What to send the frontend engineer

| Item | Where |
|---|---|
| The API base URL (tunnel address + `/api/v1`) | from ngrok; changes on restart |
| The contract | `docs/api/openapi.json`, or the live page `https://<tunnel>/docs` |
| The guide | `docs/api/frontend-guide.md` (this page too) |
| The Postman collection and environment | `docs/api/postman/` (they set `base_url` to the tunnel) |
| Demo accounts and password | the table in `docs/api-testing-postman.md` Part 1 |
| What is **not** built yet | See "Status" at the end |

## 3. Frontend side: what their app does (proposal, for the frontend engineer)

### 3.1 Settings

```env
LEMS_API_URL=https://abcd-1234.ngrok-free.app/api/v1   # changes with the tunnel
```

### 3.2 Calling the API

Every call: header `Accept: application/json` and, after sign-in, `Authorization: Bearer <token>`. Everything in `data`. Example with Laravel's HTTP client (adapt to your structure):

```php
use Illuminate\Support\Facades\Http;

// Sign in. $email/$password come from your login form.
$response = Http::baseUrl(config('services.lems.url'))
    ->acceptJson()
    ->withHeaders(['ngrok-skip-browser-warning' => '1']) // only while using the ngrok tunnel
    ->post('/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'web']);

if ($response->status() === 422 || $response->status() === 401) {
    // show $response->json('errors') per field, or "wrong email or password"
}

$login = $response->json('data');          // token, expires_at, user
session([
    'lems.token'   => $login['token'],     // server-side session, never the browser
    'lems.expires' => $login['expires_at'],
    'lems.user'    => $login['user'],      // id, name, roles, permissions, must_change_password
]);

// Any later call
$users = Http::baseUrl(config('services.lems.url'))
    ->acceptJson()
    ->withToken(session('lems.token'))
    ->get('/users', ['page' => 1, 'per_page' => 25])
    ->json();                               // data, links, meta
```

### 3.3 What the app must do with the answers

| Situation | What the frontend does |
|---|---|
| `user.must_change_password` is `true` at sign-in | Send the person to a "choose a new password" page; it calls `POST /auth/password/change` with `{current_password, password, password_confirmation}` |
| `403` with `code: password_change_required` | Same: send them to the change-password page |
| `401` `unauthenticated` | Token missing or expired (12 hours): clear the session and show sign-in |
| `401` `invalid_credentials` | "Wrong email or password" |
| `403` `forbidden` | "You do not have permission" |
| `403` `account_disabled` | Sign out and explain |
| `422` `validation_failed` | Show each message in `errors.<field>` next to its field |
| `429` | Wait the seconds in the `Retry-After` header |
| Show or hide a menu item | Use `user.permissions` (from sign-in or `GET /me`), not role names |

### 3.4 Pages the API's emails link to

| Page | Link in the email | Calls |
|---|---|---|
| `/accept-invitation?token=...` | Invitation | `POST /auth/invitations/accept` `{token, password, password_confirmation}` |
| `/reset-password?token=...&email=...` | Password reset | `POST /auth/password/reset` `{email, token, password, password_confirmation}` |
| Forgot password (their sign-in page) | | `POST /auth/password/forgot` `{email}` (always 202) |

The existing `/register` page should go: accounts are created by the college, not by the person.

## 4. The joint test (30 minutes, both people, one call)

The owner starts the API and the tunnel and sends the address. Then, in this order:

| # | Frontend does | Expect |
|---|---|---|
| 1 | `GET <base>/health` from their server | `200 {"status":"ok",...}` |
| 2 | Sign in as `student@demo.test` | `200`; token saved; `roles: ["student"]`, `permissions: []` |
| 3 | Call `GET /me` with the token | Same user, in `data` |
| 4 | Call `GET /users` as the student | `403 forbidden` shown nicely |
| 5 | Sign in as `super_admin@demo.test`, call `GET /users?per_page=5` | `200`; list with `data`, `links`, `meta` |
| 6 | Invite a user from their admin screen | `201`; the invitation email appears in `storage/logs/laravel.log` (owner reads out the token or link) |
| 7 | Open the invitation page with that token and set a password | `200`, then sign in works |
| 8 | Sign in as `first-login@demo.test` | `must_change_password: true`; the app shows the change-password page; after changing, normal access |
| 9 | Sign in with a wrong password six times | The sixth gives `429` with `Retry-After`; the app shows a waiting message |
| 10 | Sign out, then reuse the old token | `401` |
| 11 | (If the browser calls the API directly) a request from the browser | No CORS error; otherwise tell the owner the exact origin |

Afterwards the owner shows the audit log (`GET /audit-logs` as super_admin): every step above is in it.

## 5. If something goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| HTML page instead of JSON | ngrok's warning page | Send header `ngrok-skip-browser-warning: 1` |
| Timeouts or "connection refused" | Laptop asleep, or `php artisan serve`, MySQL or ngrok stopped | Restart them; the ngrok address may have changed |
| Everything stopped working after a restart | New ngrok address | Update `LEMS_API_URL` (and `APP_URL`) |
| `401` right after a good sign-in | Token not sent, or `Bearer ` spelled wrong | Header must be `Authorization: Bearer <token>` |
| `401` with a valid token after a long time | 12 hours passed | Sign in again |
| `403 password_change_required` | The account must choose a new password | Change password first |
| `429` for everyone | Rate limit is shared because the tunnel hides who is calling | Make sure `TRUSTED_PROXIES=*` and the server was restarted |
| CORS error in the browser console | Browser calls the API directly and the origin is not listed | Add it to `FRONTEND_ORIGINS`, restart; or call from the server instead |
| Mixed content / `http://` links in a page | Missing `TRUSTED_PROXIES` | Set `TRUSTED_PROXIES=*`, restart |
| Error page with technical details | `APP_DEBUG=true` | Set it to `false`, restart |

## 6. Status: what exists today

Built and tested: sign-in, tokens, forced password change, password reset, invitations, roles and permissions, account management, audit log, health. Not built yet (planned, in this order): tenancy columns, academic model, marks entry with locking and corrections, fees and receipts, announcements. If the frontend engineer's screens need data from those, they will get them in the next days; the shapes will be agreed first (D3).

## 7. After the demo

A tunnel is only for a demo. For ongoing work the API needs a stable staging server with HTTPS (D2). Then `LEMS_API_URL` stops changing, `TRUSTED_PROXIES` becomes the proxy's IP address instead of `*`, and the demo accounts are removed. See `docs/deployment.md` once it exists.
