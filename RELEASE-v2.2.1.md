# BDC v2.2.1

## Fixes

- The **Show Out of Division competitors** switch now refreshes the leaderboard immediately while preserving the selected division and role.
- The staging deployment script now preserves `config/config.php`, `config/config.local.php`, `uploads/`, `public/results/`, and `storage/`.
- Required uploads and generated-results directories are created automatically when absent.
- A release is recorded as deployed only after migrations and the health check succeed.

## Data safety

This release does not delete or replace database records. Live points and profile data remain in the database. Uploaded profile photos, registration receipts, generated results, runtime storage, and environment configuration are excluded from destructive synchronization.

Production deployment remains disabled until v2.2.1 has passed staging functional tests and an explicit Production approval is given.
