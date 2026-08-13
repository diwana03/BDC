# Bachata Dance Council Portal

BDC Portal is a server-rendered PHP 8.1 and MySQL application for competitor identity, historical points, public rankings, event registration, Registration Desk operations, and Jack & Jill scoring.

## Architecture

- `bootstrap.php` loads configuration, starts the secured session, and registers the application autoloader.
- Page controllers live in public feature folders and `admin/`.
- Shared infrastructure is in `app/Core`; domain and operational services are in `app/Services`.
- `database/schema.sql` is the clean-install base. Ordered upgrades live in `database/migrations`.
- Generated and uploaded private data belongs under `storage/`; public result artifacts belong under `public/results`.

## Supported release

- Application: 2.3.0-dev1
- PHP: 8.1 or newer
- Database: MySQL 8-compatible

## Deployment

1. Back up the database and application.
2. Deploy the release files.
3. Run `php bin/migrate.php` from the application directory.
4. Confirm that `php bin/migrate.php` reports the database is up to date.
5. Check `/health.php`; a healthy installation returns only `{"status":"ok"}`.

Migrations never run during normal HTTP requests. Applied migration checksums are stored in `bdc_schema_migrations`; never edit an applied migration.

Web access to setup, installer, patch, and rollback scripts is denied. A completed installation must contain `storage/installed.lock`.

For a new installation, create `config/config.php`, provision an empty database, then run:

```sh
BDC_INSTALL_ADMIN_PASSWORD='a strong temporary password' php bin/install.php --name='Administrator' --email='admin@example.com'
```

The password is read from the process environment and is not accepted as a command-line argument.

## Registration integrity

Public registration accepts published events only, enforces event and ticket sale windows, locks the ticket row while reserving capacity, rejects sold-out tickets, and prevents more than one active registration per event and email address.

## Testing

### Mandatory scoring development order

Every scoring change is coded on the Testing Scoreboard first and validated against isolated `bdc_test_*` data. The same validated behaviour is then implemented on the Live Scoring Dashboard, preferably through shared services and components. Testing and Live must be verified for parity and pushed together in one release. Test-only and Live-only scoring releases are prohibited. See `AGENTS.md` for the complete permanent rule.

Run all PHP syntax checks and the Relative Placement test before deployment:

```sh
find . -name '*.php' -not -path './storage/*' -print0 | xargs -0 -n1 php -l
php tests/relative-placement-v215.php
php tests/security-smoke.php
```
