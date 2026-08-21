# BDC v2.3.3-dev320

Build 3026 · Development hotfix · 22 August 2026

## Dance Cup 500 root-cause fix

- Resolves a schema-name collision with the older public `bdc_dance_cup_results` winner-history table.
- Moves the new scoring calculation rows to dedicated Test and Live `*_dance_cup_scoring_results` tables.
- Preserves the older winner-history table and all existing records without renaming, deleting or rewriting them.
- Preserves any dev319 Test calculation rows by copying them into the corrected Test table.
- Uses the corrected table consistently for calculation, ranking, checkpoints, submission and result display.

## Official BDC branding

- Uses `public/assets/bdc-logo.png` as the single official logo.
- Places the logo on a white background at the top of HTML screens through one shared branding layer.
- Covers judge links, Test, Live, Dance Cup, Jack & Jill, administration, printed HTML reports and Live Projection.
- Replaces older alternate logo paths on the public home, admin dashboard and scoring report.

## Parity and safety

- Test and Live use the same code with isolated table names.
- The new schema is idempotent and safe when the deployment migration has already run.
- `config/config.php` and Production were not changed.

## Validation

- PHP parser validation passed for the changed PHP files and migration.
- JavaScript syntax, JSON release metadata and static diff checks passed.
- Database/runtime validation remains pending on Staging after deployment.
