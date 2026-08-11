# BDC 2.3.3-dev10

## Fixes

- Restores publishing for existing Production and Staging configurations that predate the `results.storage_path` setting.
- Derives the protected account-level repository as `.bdc-results/production` or `.bdc-results/staging` when the preserved configuration does not contain that setting.
- Keeps Production and Staging result files isolated and rejects cross-environment repository paths.
- Requires the resolved repository to remain outside the application directory and to be writable before publishing begins.
- Preserves the existing atomic publication workflow for points, participant results, Heats, Final and Points documents, publication status and rollback.

## Safety

No scoring formulas, points rules, database schema or repository document format changed. A storage failure stops publication before database points are committed.
