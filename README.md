# LEMS SCNMTC Backend

API backend for the Savior Church Nursing & Midwifery Training College portal.
Laravel 13, PHP 8.3+, MySQL 8. See `setup.md` for the full project brief.

## Local setup (Windows, XAMPP)

Requirements: PHP 8.3+ (8.5 works), Composer, XAMPP MySQL running on port 3306.

```powershell
# 1. Install dependencies
composer install

# 2. Create your local env file and app key
copy .env.example .env
php artisan key:generate

# 3. Create the databases (XAMPP MySQL must be running)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS lems_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS lems_backend_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Run migrations and start the server
php artisan migrate
php artisan serve
```

Health check: <http://localhost:8000/up> (alive) and `/api/v1/ready` (database and cache work). API base: `http://localhost:8000/api/v1`.

### First login

There is no public registration, so create the first super admin from the terminal (it asks for the password, which is never stored in code):

```powershell
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan lems:create-super-admin
```

On a local machine, `php artisan db:seed` also creates one demo account per role (`<role>@demo.test`, password `password`). This never runs in staging or production.

## Quality checks

```powershell
composer ci        # Pint (style) + Larastan (static analysis) + Pest (tests)
composer fix       # auto-fix code style
```

Tests run on the `lems_backend_test` database (never on `lems_backend`).

Enable the pre-push hook once per clone so `composer ci` runs before every push:

```powershell
git config core.hooksPath .githooks
```

## Branches

Never commit to `main`. One branch per concern, merged by pull request.
