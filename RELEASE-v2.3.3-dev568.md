# v2.3.3-dev568

## Dance Cup competitor consolidation

- Keeps one comprehensive **Dance Cup Competitor** dashboard and removes the duplicate **Dance Cup Participants** navigation entry.
- Permanently redirects the old Participants URL so saved links remain safe.
- Shows one row per WDC identity with every active event and category grouped beneath it.
- Adds case-insensitive search across name, WDC ID, country, event and category.
- Adds event, category, dance style, entry type, level, status and completeness filters.
- Adds missing-photo, missing-country, unregistered and possible-duplicate review states.
- Adds photo upload for every WDC solo, couple and team without creating or modifying BDC or SDC identities.
- Shows registration, official result and championship-point history in the WDC editor.
- Blocks archival when official Dance Cup results or championship points exist.

## Data and migration status

- No database migration.
- No scoring, result, championship-point, BDC or SDC data changed.
- Existing WDC identities and registration foreign keys remain unchanged.

## Validation

- Full JavaScript regression suite: PASS.
- New WDC consolidation regression: PASS.
- Static diff and whitespace validation: PASS.
- PHP CLI syntax check: NOT RUNTIME-TESTED because PHP is unavailable in this workspace.

## Parity gate

- Testing Dance Cup scoring files: unchanged; existing automated parity checks PASS.
- Live Dance Cup scoring files: unchanged; existing automated parity checks PASS.
- Live projector files: unchanged; existing automated projector checks PASS.
- Staging runtime dashboard, upload and redirect checks: NOT RUNTIME-TESTED and required before Production promotion.

## Deployment status

- Candidate prepared for the GitHub `develop` release line.
- Production promotion remains blocked until this exact candidate passes Staging runtime checks.
