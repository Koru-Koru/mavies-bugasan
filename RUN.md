# How to run

Stack: Laravel 12 · Vue 2 · Bootstrap 5 · MySQL/MariaDB.

## Prerequisites

- PHP **8.2+**
- Composer 2.x
- MySQL **or** MariaDB (10.3+)
- Node.js 18+ (npm)

## 1. Get the code

```
git clone <repo-url> rice-and-shine
cd rice-and-shine
```

## 2. Install backend dependencies

```
composer install
```

## 3. Environment

```
copy .env.example .env
```

Edit `.env` and set at least these:

```
APP_NAME=Mavies Bugasan
APP_ENV=local
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mavies_bugasan
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log        # use log to skip sending real emails; see Mail below
```

Generate the app key:

```
php artisan key:generate
```

## 4. Database

Create the databases (the app DB plus the test DB used by the test suite):

```
mysql -u root -p -e "CREATE DATABASE mavies_bugasan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE mavies_bugasan_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Set up schema and seed data (inventory + business settings):

```
php artisan migrate --seed
```

## 5. Frontend

```
npm ci
npm run build
```

> On Node 17+, the build may fail with an OpenSSL error. If so run:
> `$env:NODE_OPTIONS="--openssl-legacy-provider"; npm run build`

## 6. Create the admin/owner account

Seeders do not create a login. Create the first admin:

```
php artisan mavies:make-owner --name="Your Name" --email="you@example.com" --password="a-strong-password"
```

## 7. Run

```
php artisan serve
```

Open **http://127.0.0.1:8000**.
- Customer side: register an account or sign in.
- Admin side: sign in at **http://127.0.0.1:8000/admin** with the owner account created in step 6.

## Tests

```
php artisan test
php artisan mavies:integrity-check
```

The test suite uses the `mavies_bugasan_test` database (see `phpunit.xml`).

## Mail (optional)

The app sends OTP, password-reset, and account emails. Default `MAIL_MAILER=log`
writes them to `storage/logs/laravel.log` instead of sending — enough to try the
flows locally. To send real mail, use a SMTP relay (e.g. Brevo):

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<your-smtp-login>
MAIL_PASSWORD=<your-smtp-key>
MAIL_FROM_ADDRESS=<a-verified-sender>
MAIL_FROM_NAME="Mavies Bugasan"
```