# Deployment — Mavies Bugasan

Internal single-server deployment. The app is a session-based SPA; no separate
API host, no queue worker required (mail is synchronous), no cron except the
daily backup.

---

## 1. Requirements

| Component | Requirement | This machine |
|---|---|---|
| PHP | **8.3** CLI (`^8.2` in composer), extensions: pdo_mysql, openssl, mbstring, gd (for dompdf), fileinfo | `D:\tools\php83\php.exe` has 8.3.33 |
| PHP (Apache) | 8.0.30 — **DO NOT use for this app**; only backs XAMPP's bundled pages | `D:\XAMPP\php\php.exe` |
| Composer | 2.x | `D:\tools\composer\composer.bat` |
| MariaDB | 10.3+ (v_orders uses scalar subqueries + ENUM + CHECK) | `D:\XAMPP\mysql` |
| Node | For building assets only; webpack 5.75 pinned | Node 24 needs `--openssl-legacy-provider` |

Start MariaDB correctly (bare `mysqld.exe` uses the wrong datadir):

```powershell
Start-Process "D:\XAMPP\mysql\bin\mysqld.exe" -ArgumentList '--defaults-file=D:\XAMPP\mysql\bin\my.ini'
```

---

## 2. Fresh install

```powershell
git clone <url> rice-and-shine
cd rice-and-shine

composer install --no-dev --optimize-autoloader
npm ci
npm run prod            # or dev; see §5 for NODE_OPTIONS

Copy-Item .env.example .env
php artisan key:generate
```

Create the database, point `.env` at it, then:

```powershell
php artisan migrate --seed
php artisan storage:link   # if using local file storage for anything
```

The default login (owner) is created with `AuthService::createOwner()` —
run once via `php artisan tinker`:

```php
app(\App\Services\AuthService::class)->createOwner('Owner', 'owner@maviesbugasan.ph', 'a-strong-password');
```

Seeders load the 15 real varieties and business settings; the real business
data lives in the existing `mavies_bugasan` database — dump it with
`database.sql` or the backup script and import instead of migrating fresh if a
copy already exists.

---

## 3. Environment (`.env`)

| Key | Value / note |
|---|---|
| `APP_ENV` | `production` (never `local` outside dev) |
| `APP_URL` | Public base URL (used to build reset-password links) |
| `APP_TIMEZONE` | `Asia/Manila` |
| `DB_*` | MySQL/MariaDB credentials — no root access on production |
| `MAIL_MAILER` | `smtp` |
| `MAIL_SCHEME` | `smtp` (Laravel 12 — this is **not** `MAIL_ENCRYPTION`) |
| `MAIL_HOST`/`MAIL_PORT` | Brevo: `smtp-relay.brevo.com:587` |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | Brevo SMTP key |
| `MAIL_FROM_ADDRESS` | A **verified** sender on Brevo |
| `SESSION_DRIVER` | `file` is fine at this scale; swap to `redis` if multi-server |

---

## 4. Mail (Brevo)

Four Mailables: `VerifyEmailMail`, `ResetPasswordMail` (customer forgot),
`CustomerPasswordResetMail` (admin reset), `StaffPasswordResetMail`.

- The From address must be **verified** in Brevo (Settings → Senders), or you
  will see `535 5.7.8 Authentication failed`.
- The SMTP key lives in `.env` only. **Rotate it** in Brevo if it was ever
  shown in chat/logs. See `SECURITY.md`.
- Mail is synchronous (`QUEUE_CONNECTION=sync`, `Mail::to()->send()`). At this
  volume that is intentional; no queue worker needed.

---

## 5. Frontend build

Mixing Laravel Mix (webpack) with a modern Node versions trips on OpenSSL 3.
Pinned `webpack@5.75.0` + `@vue/cli-service`; historically the legacy provider
is required:

```powershell
$env:NODE_OPTIONS="--openssl-legacy-provider"
npm run prod
```

Never re-add `"type": "module"` to `package.json`; the build depends on CommonJS.

---

## 6. Serving

### Option A — Laravel's built-in server

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Suitable for a single-user kiosk/store desk. **Not** for anything multi-user
long-term.

### Option B — Apache (XAMPP) with PHP-FPM/mod_php 8.3

Point the vhost `DocumentRoot` at `public/` (never the repo root) and let
`public/.htaccess` rewrite to the SPA shell. Restart Apache, verify routes:

```powershell
php artisan route:list
php artisan mavies:integrity-check
```

Because the SPA uses history-mode routing, Apache must rewrite unknown non-API
paths to the Laravel front controller (the existing `.htaccess` handles this).

---

## 7. Backups

`scripts/backup.ps1` dumps `mavies_bugasan` with `mysqldump` into
`storage/app/backups/`, keeps the last 14, and prints a summary. Run it daily
ideally via Task Scheduler:

```powershell
powershell -ExecutionPolicy Bypass -File "D:\rice-and-shine\scripts\backup.ps1"
```

**Test the restore** (`scripts/restore-check.ps1` restores the latest backup
into a throwaway database and verifies it, then drops it). Run it at least
weekly — a backup that cannot be restored is not a backup.

A one-off safe copy can also come from `database.sql`.

---

## 8. Post-deploy checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan route:list` — verify /api routes are default-deny
- [ ] `php artisan mavies:integrity-check` → all 17 PASS
- [ ] Send test mail; confirm no `535` auth failure and From verification
- [ ] Run `scripts/restore-check.ps1` against a fresh backup
- [ ] Owner account created and its password stored safely