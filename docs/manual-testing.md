# Testing the API yourself

A guided walk through everything the backend can do today. You will sign in, invite people, change roles, trigger errors on purpose, and read the audit log. Each step shows what to run and what you should see, so you can tell right away whether something is wrong.

- **Time:** about 40 minutes.
- **You need:** the XAMPP database running, and two terminal windows open in the project folder (VS Code: Terminal > New Terminal, twice).
- **Works in:** Windows PowerShell 5.1 and PowerShell 7. Every command is PowerShell.
- **Nothing here can hurt real data.** It uses your local `lems_backend` database. Emails are not sent; they are written to a log file, which is how you will "receive" them.
- **Prefer clicking to typing?** Import the Postman collection instead: see [Part 0b](#part-0b---the-postman-collection-quick-path). The PowerShell walk below goes deeper (lockout, reset links, the audit log).

## Part 0 - Prepare (once)

In the **first terminal**:

```powershell
# 1. Make sure the tables exist
php artisan migrate

# 2. Create the roles and permissions
php artisan db:seed --class=RolesAndPermissionsSeeder

# 3. Create your own super admin. It asks for a name, an email and a password (typing is hidden).
php artisan lems:create-super-admin
```

The password needs at least 10 characters, with upper and lower case letters and a digit. Remember the email and password; you will use them below.

Start the server and **leave it running** in this terminal:

```powershell
php artisan serve
```

You should see `Server running on [http://127.0.0.1:8000]`. Use the **second terminal** for everything else.

## Part 0b - The Postman collection (quick path)

`docs/api/postman/` holds a ready-made collection and a local environment.

1. In Postman: **Import** both files (`lems-backend.postman_collection.json` and `lems-local.postman_environment.json`) and select the **LEMS local** environment (top right).
2. Create the demo accounts once (local only, they all use the password `password`): `php artisan db:seed --class=DemoUsersSeeder`. There is one per role, plus `first-login@demo.test`, which must change its password before it can do anything.
3. Start the API: `php artisan serve`.
4. Open the folder **1. Sign in as...** and send one request, for example *Sign in as super_admin*. The token is saved automatically and every other request uses it.
5. Work down the folders. Each request has a short description. `List users` and `Invite a user` store `{{user_id}}` for the requests after them.

Try these three first:

| Do this | You should see |
|---|---|
| *Sign in as first-login*, then *List users* | `403`, `code: password_change_required`. Then *Change password* (sends the current password `password`) and *List users* works. |
| *Sign in as student*, then *List users* | `403`, `code: forbidden` |
| *Sign in as super_admin*, then *List audit entries* | `200` with every sign-in and change so far |

The collection is a convenience. The OpenAPI spec (`docs/api/openapi.json`) is the source of truth.

## Part 1 - Set up the helpers (once per terminal window)

Paste this whole block into the second terminal. It defines three small functions so you don't have to type long commands. Change the first three lines to your own details.

```powershell
$base = "http://localhost:8000/api/v1"
$adminEmail = "you@example.com"          # the email you gave to lems:create-super-admin
$adminPassword = "your-password-here"    # the password you chose

# Api <METHOD> <PATH> [body] [-Token <token>]  : sends a request and prints the answer
function Api {
    param([string]$Method, [string]$Path, $Body = $null, [string]$Token = "")
    $headers = @{ Accept = "application/json" }
    if ($Token) { $headers["Authorization"] = "Bearer $Token" }
    $params = @{ Method = $Method; Uri = "$base$Path"; Headers = $headers }
    if ($null -ne $Body) {
        $params["Body"] = ($Body | ConvertTo-Json -Depth 5)
        $params["ContentType"] = "application/json"
    }
    try {
        $response = Invoke-RestMethod @params
        if ($null -eq $response -or $response -eq "") { "OK (no content)" } else { $response | ConvertTo-Json -Depth 6 -Compress }
    } catch {
        $status = [int]$_.Exception.Response.StatusCode
        $text = $_.ErrorDetails.Message
        if (-not $text -and $_.Exception.Response.GetResponseStream) {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $text = $reader.ReadToEnd()
        }
        "HTTP $status  $text"
    }
}

# Get-Token <email> <password> : signs in and returns just the token
function Get-Token {
    param([string]$Email, [string]$Password)
    $body = @{ email = $Email; password = $Password } | ConvertTo-Json
    (Invoke-RestMethod -Method Post -Uri "$base/auth/login" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $body).data.token
}

# Get-EmailToken <page> : finds the newest emailed link ("accept-invitation" or "reset-password") in the mail log
function Get-EmailToken {
    param([string]$Page)
    $hit = Select-String -Path "storage\logs\laravel.log" -Pattern "$Page\?token=([A-Za-z0-9]+)" | Select-Object -Last 1
    $hit.Matches[0].Groups[1].Value
}
```

How to read an answer: a successful call prints JSON. A failed call prints `HTTP <number>` followed by the error JSON. Every error looks the same: `{"message":..., "errors":..., "code":...}`.

## Part 2 - Is it alive?

```powershell
Api GET /ready
```

**Expect:** `{"status":"ready","checks":{"database":"ok","cache":"ok"}}`.
If it says `unavailable` with a 503, the database is down: start it in XAMPP.

```powershell
Api GET /health
```

**Expect:** `{"status":"ok","version":"...","time":"..."}`. This one only says the app is running; it does not look at the database.

Also open <http://localhost:8000/up> in a browser. It answers with a short page when the app itself is running.

## Part 3 - Signing in

**3.1 Wrong password**

```powershell
Api POST /auth/login @{ email = $adminEmail; password = "wrong" }
```

**Expect:** `HTTP 401` with `"code":"invalid_credentials"`.

**3.2 Missing fields**

```powershell
Api POST /auth/login @{ email = "not-an-email" }
```

**Expect:** `HTTP 422`, `"code":"validation_failed"`, and an `errors` object naming the `email` and `password` fields.

**3.3 Sign in properly**

```powershell
$admin = Get-Token $adminEmail $adminPassword
$admin
```

**Expect:** a long text (the token) is printed. Keep it in `$admin`; it proves who you are for the next 12 hours.

**3.4 Who am I?**

```powershell
Api GET /me -Token $admin
```

**Expect:** your name and email, `"status":"active"`, `"roles":["super_admin"]`, and a `permissions` list (`users.view`, `users.invite`, ..., `audit.view`). The frontend uses this list to decide which screens to show.

**3.5 Without a token**

```powershell
Api GET /me
```

**Expect:** `HTTP 401`, `"code":"unauthenticated"`.

## Part 4 - Inviting people

**4.1 See which roles you can give**

```powershell
Api GET /roles -Token $admin
```

**Expect:** the eight roles (`super_admin`, `head_of_academics`, `academic_affairs`, `ict_admin`, `registrar`, `bursar`, `lecturer`, `student`) in `data`, and `meta.can_assign` set to `true`.

**4.2 Invite a lecturer**

```powershell
Api POST /users/invite @{ name = "Ama Lecturer"; email = "ama@example.com"; roles = @("lecturer") } -Token $admin
```

**Expect:** `"status":"invited"` and `"roles":["lecturer"]`. No password exists yet.

**4.3 "Read" the email**

Nothing is sent to the internet. The email was written to `storage/logs/laravel.log`. Open that file and search for `Accept invitation`, or just pull out the token:

```powershell
$inviteToken = Get-EmailToken "accept-invitation"
$inviteToken.Length
```

**Expect:** `64`. In real life this token is inside the link the person clicks: `<frontend>/accept-invitation?token=...`.

**4.4 Accept the invitation (as Ama)**

```powershell
Api POST /auth/invitations/accept @{ token = $inviteToken; password = "Lecturer-Passw0rd-1"; password_confirmation = "Lecturer-Passw0rd-1" }
```

**Expect:** `data.message` "Your account is ready. Please sign in." and `data.user.status` `"active"`.

**4.5 Use the same link again**

Run the same command again.

**Expect:** `HTTP 422`, `"code":"invalid_token"`. A link works once.

**4.6 Sign in as Ama**

```powershell
$ama = Get-Token "ama@example.com" "Lecturer-Passw0rd-1"
Api GET /me -Token $ama
```

**Expect:** `"roles":["lecturer"]` and `"permissions":[]`. A lecturer holds no permissions yet.

**4.7 Ama tries to manage users**

```powershell
Api GET /users -Token $ama
```

**Expect:** `HTTP 403`, `"code":"forbidden"`.

## Part 5 - Finding and changing users

**5.1 List, search and filter**

```powershell
Api GET /users -Token $admin
Api GET "/users?role=lecturer" -Token $admin
Api GET "/users?q=ama" -Token $admin
Api GET "/users?status=invited&sort=-created_at&per_page=5" -Token $admin
```

**Expect:** each answer has `data` (the users), `links` and `meta` (page numbers and the total). The filters narrow the list.

**5.2 Bad filters are refused**

```powershell
Api GET "/users?sort=password" -Token $admin
Api GET "/users?per_page=1000" -Token $admin
```

**Expect:** `HTTP 422` for both.

**5.3 Change Ama's roles**

First get her id (a 26-character text, never a number):

```powershell
$amaId = (Invoke-RestMethod -Uri "$base/users?q=ama" -Headers @{ Accept = "application/json"; Authorization = "Bearer $admin" }).data[0].id
$amaId
```

Then:

```powershell
Api PUT "/users/$amaId/roles" @{ roles = @("lecturer", "registrar") } -Token $admin
```

**Expect:** `"roles":["lecturer","registrar"]`. The call sets the complete list, it does not add to it.

**5.4 Rename her**

```powershell
Api PATCH "/users/$amaId" @{ name = "Ama Mensah" } -Token $admin
```

**Expect:** the new name.

## Part 6 - Rules that protect you

**6.1 A head of academics cannot create a super admin**

```powershell
Api POST /users/invite @{ name = "Head Person"; email = "head@example.com"; roles = @("head_of_academics") } -Token $admin
$headToken = Get-EmailToken "accept-invitation"
Api POST /auth/invitations/accept @{ token = $headToken; password = "Head-Passw0rd-123"; password_confirmation = "Head-Passw0rd-123" }
$head = Get-Token "head@example.com" "Head-Passw0rd-123"
```

Now act as the head:

```powershell
# can invite a bursar:
Api POST /users/invite @{ name = "Bea Bursar"; email = "bea@example.com"; roles = @("bursar") } -Token $head
# cannot invite a super admin:
Api POST /users/invite @{ name = "X"; email = "x@example.com"; roles = @("super_admin") } -Token $head
# cannot edit you (the super admin):
$myId = (Invoke-RestMethod -Uri "$base/me" -Headers @{ Accept = "application/json"; Authorization = "Bearer $admin" }).data.id
Api PATCH "/users/$myId" @{ name = "Hacked" } -Token $head
# cannot read the audit log:
Api GET /audit-logs -Token $head
```

**Expect:** the first works (`"status":"invited"`). The next three each answer `HTTP 403`, `"code":"forbidden"`.

**6.2 Disabling an account signs the person out at once**

```powershell
Api POST "/users/$amaId/disable" -Token $admin     # status becomes "disabled"
Api GET /me -Token $ama                             # Ama's old token no longer works
Api POST /auth/login @{ email = "ama@example.com"; password = "Lecturer-Passw0rd-1" }
```

**Expect:** `"status":"disabled"`, then `HTTP 401` `unauthenticated`, then `HTTP 401` `invalid_credentials`.

Bring her back:

```powershell
Api POST "/users/$amaId/enable" -Token $admin      # status becomes "active" again
```

**6.3 You cannot lock the college out**

```powershell
Api POST "/users/$myId/disable" -Token $admin
Api PUT "/users/$myId/roles" @{ roles = @("lecturer") } -Token $admin
```

**Expect:** `HTTP 422` `cannot_disable_self`, then `HTTP 409` `last_super_admin`. The last active super admin can never be disabled or demoted.

## Part 7 - Passwords

**7.1 Too many wrong passwords (lockout)**

Use an email that does not exist, so no real account is affected:

```powershell
1..6 | ForEach-Object { "try $_ : " + (Api POST /auth/login @{ email = "ghost@example.com"; password = "wrong$_" }) }
```

**Expect:** tries 1 to 5 give `HTTP 401` `invalid_credentials`; try 6 gives `HTTP 429` `too_many_requests`. A made-up email locks exactly like a real one, so nobody can use this to find out who has an account. The lock lasts 15 minutes. To clear it right now: `php artisan cache:clear`.

If you ever get 429 on everything, you hit the limit of 20 public requests per minute: wait a minute.

**7.2 Forgot password**

```powershell
Api POST /auth/password/forgot @{ email = $adminEmail }
Api POST /auth/password/forgot @{ email = "ghost@example.com" }
```

**Expect:** both answer `202` with the *same* message, even though the second email does not exist.

**7.3 Reset with the emailed token**

```powershell
$resetToken = Get-EmailToken "reset-password"
Api POST /auth/password/reset @{ email = $adminEmail; token = $resetToken; password = "Reset-Passw0rd-42"; password_confirmation = "Reset-Passw0rd-42" }
```

**Expect:** "Your password has been reset." Now check that everything signed in before is out:

```powershell
Api GET /me -Token $admin
```

**Expect:** `HTTP 401`. Reusing `$resetToken` also fails with `invalid_token`.

**7.4 Change your password**

```powershell
$admin = Get-Token $adminEmail "Reset-Passw0rd-42"
Api POST /auth/password/change @{ current_password = "Reset-Passw0rd-42"; password = "short"; password_confirmation = "short" } -Token $admin
Api POST /auth/password/change @{ current_password = "Reset-Passw0rd-42"; password = "Changed-Passw0rd-7"; password_confirmation = "Changed-Passw0rd-7" } -Token $admin
```

**Expect:** the first answers `HTTP 422` with three messages about the 10 character, upper/lower case and digit rules. The second answers "Your password has been changed."

**7.5 Accounts that must change their password**

Accounts created by the college with a password someone else chose (the seeded demo users, and later bulk-created students) are flagged `must_change_password`. Try the demo one (run `php artisan db:seed --class=DemoUsersSeeder` first):

```powershell
$first = Get-Token "first-login@demo.test" "password"
Api GET /me -Token $first
Api GET /users -Token $first
Api POST /auth/password/change @{ current_password = "password"; password = "First-Passw0rd-2026"; password_confirmation = "First-Passw0rd-2026" } -Token $first
Api GET /me -Token $first
```

**Expect:** `/me` works and shows `"must_change_password":true`. `/users` answers `HTTP 403` with `"code":"password_change_required"`. After the change, `/me` shows `false`. The only calls allowed before the change are `/me`, change password and sign out. To repeat the test, run the demo seeder again: it puts this account back to `password` with the flag set.

**7.6 Sign out**

```powershell
Api POST /auth/logout -Token $admin
Api GET /me -Token $admin
```

**Expect:** `OK (no content)`, then `HTTP 401`. Sign in again for the next part:

```powershell
$adminPassword = "Changed-Passw0rd-7"
$admin = Get-Token $adminEmail $adminPassword
```

## Part 8 - The audit log

Every important action above was recorded. Read it:

```powershell
Api GET "/audit-logs?per_page=5" -Token $admin
Api GET "/audit-logs?event=user.roles_changed" -Token $admin
Api GET "/audit-logs?event=auth.*" -Token $admin
Api GET "/audit-logs?subject_id=$amaId" -Token $admin
```

**Expect:** newest first. The roles entry shows `"before":{"roles":["lecturer"]}` and `"after":{"roles":["lecturer","registrar"]}`, who did it (`actor`), and the IP address. `auth.*` shows logins, failed logins, the lockout, password reset and change, and logout. Look for passwords and tokens: they are never recorded.

Other roles cannot read this (you saw `403` for the head of academics in 6.1), and there is no way to edit or delete an entry through the API.

## Part 9 - Use the browser instead

The API documents itself, with a **Try it** button:

1. Open <http://localhost:8000/docs>.
2. Browse the endpoints on the left, grouped as Authentication, Users, Audit log, Health.
3. Open an endpoint and use its **Try it** panel. Protected endpoints need your token: paste it into the authorization field of that panel (type `$admin` in the terminal to print it). Public ones, such as sign in, need no token.

The Postman collection in `docs/api/postman/` does the same with saved sign-ins (see Part 0b).

## Part 10 - Run the automated tests

In the second terminal:

```powershell
composer ci
```

**Expect:** style check passes, static analysis passes with 0 errors, and all tests pass (157 tests at the time of writing). The tests run on a separate database (`lems_backend_test`) and never touch your data.

## Checklist

Tick each one as you go.

| # | You saw | Done |
|---|---|---|
| 2 | `/ready` says ready and `/health` says ok | [ ] |
| 3.1 | wrong password gives 401 `invalid_credentials` | [ ] |
| 3.2 | missing fields give 422 with field errors | [ ] |
| 3.4 | `/me` shows your roles and permissions | [ ] |
| 3.5 | no token gives 401 `unauthenticated` | [ ] |
| 4.4 | invitation accepted, account active | [ ] |
| 4.5 | the same link a second time gives 422 `invalid_token` | [ ] |
| 4.7 | a lecturer gets 403 on `/users` | [ ] |
| 5.1 | list, search and filter work | [ ] |
| 5.3 | roles replaced, the list is complete | [ ] |
| 6.1 | head of academics cannot invite or edit a super admin | [ ] |
| 6.2 | disabling signs the person out immediately | [ ] |
| 6.3 | you cannot disable yourself or the last super admin | [ ] |
| 7.1 | the sixth wrong try gives 429, even for a made-up email | [ ] |
| 7.2 | password/forgot answers the same for real and fake emails | [ ] |
| 7.3 | reset works once and signs everything out | [ ] |
| 7.4 | weak passwords are refused with clear messages | [ ] |
| 7.5 | a `must_change_password` account is blocked (403 `password_change_required`) until it changes its password | [ ] |
| 8 | the audit log shows before and after, who and where | [ ] |
| 10 | `composer ci` is all green | [ ] |

## If something goes wrong

| What you see | Likely cause | Fix |
|---|---|---|
| `Unable to connect to the remote server` | `php artisan serve` is not running | Start it in the first terminal |
| `/ready` says `unavailable` or a 503, or errors mention "connection refused" | The database is stopped | Start MySQL in the XAMPP control panel |
| `SQLSTATE ... Base table or view not found` | Migrations not run | `php artisan migrate` |
| `HTTP 429` on everything | More than 20 public requests in a minute, or a login lock | Wait a minute, or `php artisan cache:clear` |
| `Cannot index into a null array` or empty `$inviteToken` | You have not run the invite step yet, or you are not in the project folder | Run 4.2 first; `cd` to the project folder |
| Everything returns 401 after a long break | The token expired (12 hours) | `$admin = Get-Token $adminEmail $adminPassword` |
| `HTTP 403` for the super admin | The account does not have the `super_admin` role | Create it with `php artisan lems:create-super-admin` |
| Colors or odd characters in the output | The terminal font | Harmless |

## Starting over

To reset your local data and begin again (this **deletes everything** in the local `lems_backend` database, so only do it on a machine with no real data):

```powershell
php artisan migrate:fresh
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan lems:create-super-admin
```
