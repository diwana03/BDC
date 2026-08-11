# Bluehost deployment

1. Extract all ZIP contents directly into `/public_html/bachatadancecouncil/portal`.
2. Preserve the existing `config/config.php` file.
3. Visit `/portal/health.php`, then log in at `/portal/admin/` once. This runs the automatic database upgrade.
4. Open `/portal/admin/results/` to manage official PDF, CSV and World Result records.
5. Public results are available at `/portal/results/`.

For uploaded repository files, ensure `/portal/storage/results` is writable (typically 755; use 775 only if required by the host).

## Automated staging deployment

Use `bin/deploy-staging.sh` as the canonical staging worker. It locks concurrent runs, deploys the exact `origin/develop` commit, preserves environment configuration and user-generated data, runs migrations, and records the commit only after a successful health check.

The deployment intentionally preserves:

- `config/config.php` and `config/config.local.php`
- `uploads/` profile photos
- `public/results/` generated and published results
- `storage/` runtime files and registration receipts
