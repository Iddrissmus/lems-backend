# Testing the API with Postman

Use this page to check, in about 10 minutes, that the whole API works as the frontend team will expect. It uses the Postman collection in `docs/api/postman/`. For a slower, deeper walk in PowerShell (lockout, email links, the audit log in detail) see `docs/manual-testing.md`.

| You want to | Do this |
|---|---|
| Know in one click that everything works | Part 3: run folder **9. Automated checks** |
| Try requests by hand | Part 4: the folders **1 to 4** |
| Check the two email-link flows (invitation, password reset) | Part 5 |
| Repeat the checks from the command line | Part 7 (Newman) |

## Part 1 - Set up (once)

1. **Start the database** (XAMPP control panel > MySQL > Start). If Start keeps failing, tell the developer; see "If something goes wrong".
2. In the project folder (PowerShell):

   ```powershell
   php artisan migrate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   php artisan db:seed --class=DemoUsersSeeder
   php artisan serve
   ```

   Leave `php artisan serve` running. The API is now at `http://localhost:8000/api/v1`.
3. **Postman:** Import > choose both files from `docs/api/postman/`:
   - `lems-backend.postman_collection.json`
   - `lems-local.postman_environment.json`
4. Top right of Postman, pick the environment **LEMS local**. (Its only setting is `base_url`. If the API runs elsewhere, for example through a tunnel, change `base_url` there.)

The demo accounts are synthetic and exist only locally. All of them use the password `password`:

| Email | Role | What to expect |
|---|---|---|
| `super_admin@demo.test` | super_admin | Everything, including the audit log |
| `head_of_academics@demo.test` | head_of_academics | Manages accounts, cannot make super admins, cannot read the audit log |
| `ict_admin@demo.test` | ict_admin | Same rights as head_of_academics |
| `academic_affairs@demo.test` | academic_affairs | No permissions yet (arrive with the results module) |
| `registrar@demo.test`, `bursar@demo.test`, `lecturer@demo.test`, `student@demo.test` | the role named | No permissions yet |
| `first-login@demo.test` | lecturer | **Must change its password first**: every call except `/me`, change password and sign out answers 403 |

## Part 2 - How the collection is organised

| Folder | What it is |
|---|---|
| 0. Health | `/health` and `/ready`, no sign-in needed |
| 1. Sign in as... | One request per demo account. Sending one **saves the token**; every other request then uses it |
| 2. Authentication | `/me`, change password, sign out, forgot and reset password, accept invitation |
| 3. Users and roles | List, invite, read, rename, set roles, disable, enable |
| 4. Audit log | Read the audit trail (super_admin only) |
| 9. Automated checks | A scripted walk through the whole flow, 35 steps, each with assertions |

Every request carries tests. After you send one, open the **Test Results** tab: green means the answer was what the contract promises (status code and shape). Red means something is wrong. Each request's **Description** says what it does and which permission it needs.

## Part 3 - One-click check (about 1 minute)

1. Re-seed the demo accounts so they are in their starting state: `php artisan db:seed --class=DemoUsersSeeder`.
2. In Postman, right-click the folder **9. Automated checks** > **Run folder** (or Collections > Runner, select only that folder) > **Run**.
3. **Expect: 35 requests, 0 failed.** Every line is green.

What the 35 steps prove:

| Steps | They prove |
|---|---|
| A01-A02 | The app and its database and cache are up |
| A03-A06 | Wrong password gives 401 `invalid_credentials`; missing fields give 422 with field errors; no token gives 401 `unauthenticated`; the old route names are gone (404) |
| A07-A11 | Sign-in works and returns a token with an expiry; `/me` shows roles and permissions; the role list; paging; a bad filter is refused with 422 |
| A12-A17 | A super admin invites a lecturer, renames them, replaces their roles, disables and re-enables them. Inviting an email that already has an account is refused (409) |
| A18-A19 | You cannot disable yourself (422 `cannot_disable_self`); the last super admin cannot be demoted (409 `last_super_admin`) |
| A20 | The audit log recorded the sign-in, the invitation and the role change, with before and after, and never contains a password |
| A21-A24 | A head of academics can list users but cannot invite a super admin (403) or read the audit log (403) |
| A25-A27 | A student cannot list users (403) and holds no permissions |
| A28-A30 | `first-login` is told to change its password and is blocked everywhere else (403 `password_change_required`) |
| A31-A33 | An ICT admin can list users but not read the audit log |
| A34-A35 | After sign-out the token stops working (401) |

It leaves one extra invited test user behind each run and does not change any demo password, so you can run it as often as you like. (Sign-in is limited to 20 requests a minute per IP; if you run it many times in a row and see 429, wait a minute.)

## Part 4 - Try it by hand

1. Folder **1**, send **Sign in as super_admin**. Look at the Test Results tab (green) and the response: `data.token`, `data.expires_at`, and `data.user` with `roles`, `permissions` and `must_change_password`.
2. Folder **2**, send **Current user (/me)**.
3. Folder **3**, send **List users**, then **Invite a user**, **Get a user**, **Rename a user**, **Set a user's roles**, **Disable a user**, **Enable a user**, in that order. **List users** and **Invite a user** save the user id for the ones after them.
4. Folder **4**, send **List audit entries**. Turn on the disabled parameters (tick them) to filter by event, subject or date.
5. See permissions at work: send **Sign in as student**, then **List users**. **Expect** 403 `forbidden`. Then **Sign in as first-login** and **List users**: **expect** 403 `password_change_required`; send **Change password** and try again.

Tip: the **Console** (View > Show Postman Console) shows the roles of whoever just signed in.

## Part 5 - The two email-link flows

Nothing is sent to the internet. In local, emails are written to `storage/logs/laravel.log`, and each contains a link with a token.

**Invitation**
1. Folder 3: **Invite a user** (note the email in the body; it is made unique each time).
2. Get the token (PowerShell, in the project folder):

   ```powershell
   Select-String -Path storage\logs\laravel.log -Pattern 'accept-invitation\?token=([A-Za-z0-9]+)' | Select-Object -Last 1 | ForEach-Object { $_.Matches[0].Groups[1].Value }
   ```
3. Folder 2: **Accept invitation**. Replace `<token from the invitation email link>` in the body with that token, and set a password of your own (10+ characters, upper and lower case, a digit). **Expect** 200 and `data.user.status` `active`. Send it again: **expect** 422 `invalid_token` (a link works once).

**Forgot and reset password**
1. Folder 2: **Forgot password** (**expect** 202; change the email to try one that does not exist: the answer is identical, on purpose).
2. Get the token with the same command, replacing `accept-invitation` with `reset-password`.
3. **Reset password**: paste the token and a new password. **Expect** 200. Everything signed in before is signed out.

These two cannot be in the one-click run because the token only exists inside the email.

## Part 6 - What "everything is working" looks like

| Check | Expected |
|---|---|
| Folder 9 | 35 requests, 0 failed |
| Folders 0, 1 | All green (every demo account signs in) |
| Folders 3, 4 as `super_admin` | All green |
| Part 5, both flows | Accept invitation 200 then 422; reset 200 |
| `composer ci` | Pint, Larastan and all Pest tests pass (162 at the time of writing) |

## Part 7 - Command line (optional)

For a repeatable run without Postman's window, for example before sending something to the frontend team:

```powershell
npx newman@6.2.1 run docs/api/postman/lems-backend.postman_collection.json `
  -e docs/api/postman/lems-local.postman_environment.json `
  --folder "9. Automated checks (run this folder in the Collection Runner)"
```

It prints each request and a final table. **Expect** `failed` to be 0 everywhere.

## Last verified

2026-11-20, on the `phase-1/auth-contract` branch:

| Layer | Result |
|---|---|
| Automated tests (`composer ci`) | 162 passed, Pint and Larastan clean |
| Live end-to-end script against a running API (148 checks: routing, CORS, every role, invitations, passwords, lockout, audit) | 148 passed |
| Postman collection run with Newman | Folder 9: 35 requests, 68 assertions, 0 failed. Folders 0, 1, 2, 3, 4: 0 failed |

The live testing found and fixed one real bug: login answered `expires_at: null` although tokens do expire after 12 hours. It now reports the real time.

## If something goes wrong

| What you see | Likely cause | Fix |
|---|---|---|
| `Could not get response` / connection refused | `php artisan serve` is not running | Start it |
| 503 on `/ready`, or errors about the database | MySQL is stopped | Start it in XAMPP |
| Sign-in answers 401 for a demo user | Demo accounts missing, or an earlier test changed a password | `php artisan db:seed --class=DemoUsersSeeder` |
| Everything answers 403 `password_change_required` | The last token belongs to `first-login` | Send **Sign in as super_admin** again |
| 401 `unauthenticated` after a while | The token lasts 12 hours, or you signed out | Sign in again |
| 429 `too_many_requests` | More than 20 sign-ins a minute, or 5 wrong passwords for one email | Wait a minute (a locked email: 15 minutes, or `php artisan cache:clear` locally) |
| Folder 9 fails at A28-A30 | `first-login` already changed its password in an earlier test | Re-seed the demo users (`php artisan db:seed --class=DemoUsersSeeder`) and run again |
| The XAMPP MySQL panel keeps stopping | Cause not found (it happened on 2026-11-20) | Start it from a terminal: `C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --console` and leave it open |
