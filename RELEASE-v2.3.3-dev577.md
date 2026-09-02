# v2.3.3-dev577

## Single premium Dance Cup Competitor workspace

- Replaces the actual `competitors.php` page with the premium WDC dashboard.
- Removes the separate Premium Preview page and navigation entry.
- Retains the exact Production-proven WDC identity query.
- Adds case-insensitive search, entry/style/profile filters, sorting, missing-photo/country review, duplicate indicators, completeness, CSV export and profile/photo actions.
- Redesigns the actual WDC editor and removes the editable raw Photo URL.
- Adds a WDC photo studio with replacement upload, drag, zoom, crop and reposition.

## PHP quality gate

- Adds GitHub Actions PHP 8.1 lint for every PHP source file.
- Runs the complete JavaScript regression suite in the same candidate gate.
- Repairs the pre-existing invalid arrow-function return declaration found by the new PHP gate in `GoogleFormSyncService`.
- Repairs the pre-existing malformed competitor-role ternary found by the PHP gate in `EventIntegrationService`.
- Repairs the pre-existing missing statement terminator found by the PHP gate in the automatic scoring live-data endpoint.

## Safety

- No database migration.
- BDC and SDC identities/photos remain isolated from WDC edits.
- Scoring, results and championship-point calculations are unchanged.

## Validation

- Local JavaScript regression suite: required before candidate publication.
- Candidate PHP 8.1 CI: required before merging to `develop`.
- Staging dashboard/editor/photo runtime test: required before Production approval.
