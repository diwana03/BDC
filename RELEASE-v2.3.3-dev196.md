# BDC 2.3.3-dev196

## Testing mode selection restored

- Restores the Scoring Tests landing screen with Manual Scoring, Automatic Scoring, and Test Projector choices.
- Routes Manual Scoring to the clean `panel.php` entry introduced in dev195.
- Routes Automatic Scoring to the existing `automatic-screen.php` workflow.
- Returns both admin dashboard navigation variants to the mode-selection screen.

## Safety

- Testing-only random generators remain available only in Testing.
- Live receives no random generator controls.
- Testing remains isolated through `bdc_test_*` tables.
- No scoring calculations or database schema were changed.

## Validation

- `strict_types` remains the first executable PHP declaration.
- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev196 to Staging through Release Manager.
- Production was not deployed or modified.
