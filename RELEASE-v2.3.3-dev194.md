# BDC 2.3.3-dev194

## Testing dashboard 500 hotfix

- Fixes the HTTP 500 introduced on the Scoring Tests mode-selection page in dev193.
- Restores `declare(strict_types=1)` as the first executable PHP statement.
- Points both admin dashboard navigation variants directly to the clean, uncached Manual Testing dashboard path.
- Retains the confirmed Bluehost cache and double-compression safeguards from dev193.

## Safety

- Does not change scoring calculations, judges, competitors, marks, results, points, or database tables.
- Live scoring code and Live data are unchanged.

## Validation

- Reproduced HTTP 500 specifically on `select-mode.php` while the direct Testing dashboard route remained healthy.
- Identified the PHP declaration-order violation from the dev193 emergency redirect.
- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev194 to Staging through Release Manager.
- Production was not deployed or modified.
