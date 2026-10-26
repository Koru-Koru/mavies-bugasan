# Security — Mavies Bugasan

Security model of this application, written for whoever runs it. It is a
single-server, session-based SPA with no token auth and no external API.

---

## 1. Authentication

- Two **session guards**: `admin` and `customer`. A session for one guard can
  never satisfy a check against the other (`tests/Feature/HttpAuthTest.php`
  proves this).
- Passwords are always **bcrypt** (`Hash::make`). Integrity check **#13**
  fails the moment anything other than a bcrypt hash lands in a password
  column.
- Account lockout: repeat auth failures are rate-limited, not hard-locked
  (lockouts are intentionally reversible — see business rules).
- Sessions regenerate (`session()->regenerate()`) on login, logout, and
  registration so no fixation is possible. When the CSRF token rotates, the
  SPA transparently re-fetches `/api/csrf-token` and replays the request.

## 2. Route policy — default deny

`routes/web.php` is written default-deny. The **only** public routes are:

```
POST admin/login · customer/login · customer/register · customer/register/otp
POST customer/otp/verify · customer/password/forgot · customer/password/reset
GET  catalog · shop · csrf-token
```

Everything else sits behind `auth:admin` or `auth:customer`. The base SPA
route falls through to the Blade shell; all `/api/*` is guarded. The
`{"any?}` catch-all explicitly excludes `api|storage|build`.

## 3. CSRF

- Every state-changing request carries the session CSRF token
  (`X-CSRF-TOKEN`, seeded from the Blade `meta` tag).
- A mismatched/stale token (`419`) is retried once against a fresh token.
- GET reports/export links are read-only downloads; they work because they do
  not mutate state.

## 4. Verification & reset tokens

- Email-verification OTPs and password-reset tokens are random
  (`random_int` / `Str::random(64)`), single-use, time-boxed
  (OTP **10 min**, reset token **60 min**), and **stored only as SHA-256
  hashes** — a database read cannot recover a token.
- Reusing/expiring a token is refused with the same generic `422` as a wrong
  token; `password/forgot` returns an identical response whether or not the
  email exists (**no user enumeration**).
- Admin-generated temp passwords are emailed to the user's verified address
  and must be changed by the recipient.

## 5. Rate limits

`throttle:` middleware: `login`, `register`, `otp`, `place-order`. Limits block
credential stuffing and OTP brute force. The buckets are defined in
`app/Providers/AppServiceProvider.php` (`RateLimiter::for('login'|'register'|'otp'|'place-order')`)
and applied to the routes in `routes/web.php`.

## 6. Error handling

`bootstrap/app.php` renders JSON for all API errors:

- `DomainException` → **422** with a short message and context.
- anything else → generic message + reference code. **No stack traces,
  vendor paths, SQL, or session internals ever reach the client.**

## 7. Auditability

- `transaction_log` records actor type/id/label, action, entity, IP, timestamp
  for every sensitive mutation (login, OTP issue/verify, reset, password
  change, order state changes, payments, stock, damage, blacklist…).
- `order_status_history` records every order transition.
- Reports and the dashboard agree with `v_orders`, so money can be verified
  independently of the code that produced it.

## 8. Secrets and this repo

- `.env` is gitignored (`.gitignore:9`). **Never commit `.env`.**
- Brevo SMTP credentials live only in `.env`.
- **Action required if you ever shared a secret in chat/logs:** the Brevo SMTP
  key shown earlier in the build session (`xsmtpsib-…-XfrWOlMaQGBKZmey`) must
  be **regenerated** in Brevo and replaced in `.env`. Also confirm the sender
  `qaborquiola@tip.edu.ph` is verified as a Sender in Brevo, or TLS auth will
  keep failing with `535 5.7.8`.
- `database.sql` and `storage/app/backups/*.sql` contain business data but no
  plaintext passwords (bcrypt + SHA-256 hashes only). Back these up off the
  machine if the threat model demands it.

## 9. Operational checks

On every deploy:

```powershell
php artisan mavies:integrity-check     # 17 PASS
php artisan route:list                 # confirm /api stays default-deny
php artisan test                       # 78 tests, 300+ assertions
scripts/restore-check.ps1              # prove a backup restores
```

## 10. Reporting

This is internal software for a single business. Report suspected issues
directly to the owner (do not open public bug trackers) and rotate the SMTP
key + owner/staff passwords as a precaution.