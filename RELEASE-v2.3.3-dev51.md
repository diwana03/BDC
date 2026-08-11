# BDC v2.3.3-dev51

## Staging Migration Fix

- Fixes the Automatic Scoring setup migration that caused `bin/migrate.php` to exit 255 during the dev50 Staging deployment.
- `bdc_scoring_round_setup.round_id` now derives the exact SQL integer type used by `bdc_scoring_rounds.id` before creating the foreign key.
- If a legacy database cannot accept the foreign key, the setup table is created without that constraint instead of blocking deployment.
- Automatic Scoring setup/draft/confirmation behavior from dev50 is unchanged.
- Release Manager workflow is unchanged.
