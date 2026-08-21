# BDC v2.3.3-dev319

Build 3025 · Development hotfix · 22 August 2026

## Dance Cup scoring recovery

- Fixes the HTTP 500 shown when a Dance Cup category is opened after application code was updated without the matching database migration completing.
- Safely provisions only the missing isolated Dance Cup scoring tables before the Test or Live workspace reads them.
- Applies the same protection to the Dance Cup judge-order endpoint.
- Returns a controlled setup message instead of a raw server error if database schema creation is unavailable.

## Parity gate

- Test and Live use the same recovery method with separate table prefixes.
- Existing Dance Cup events, categories, criteria and scoring data are preserved.
- `config/config.php` and Production were not changed.

## Validation

- Static diff and JavaScript syntax checks passed.
- PHP CLI is unavailable in the workspace, so PHP runtime and database validation remain pending on Staging.
