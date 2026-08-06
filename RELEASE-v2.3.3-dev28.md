# BDC 2.3.3-dev28, build 2358

## Read-only Storage Usage diagnostics

- Adds a Super Admin-only Storage Usage page.
- Reports server filesystem totals, database size and BDC tracked storage.
- Breaks usage down across backup types, deployment snapshots, competitor photos, registration receipts, logs, results and the development repository.
- Ranks the most likely causes of rapid storage growth.
- Lists the 50 largest tracked files, old file counts and incomplete temporary backup files.
- Enforces scan time and file-count limits to avoid freezing the Admin portal on large hosting accounts.
- Makes no deletions, retention changes, uploads or external-storage changes.

Push to `develop` only. Deploy to Staging manually through Release Manager. Production is unchanged.
