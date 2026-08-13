# BDC 2.3.3-dev195

## Authenticated Testing dashboard 500 fix

- Adds `admin/scoring-tests/panel.php` as a clean direct entry point for Manual Testing.
- Loads the proven isolated Testing scoring engine directly instead of capturing it through the legacy `dashboard.php` output wrapper.
- Runs the compression safeguards before any Testing output buffer is created.
- Updates both admin navigation variants and sandbox recovery to use the clean entry point.
- Retains the route-level Bluehost cache and double-compression protection.

## Safety

- The clean entry still uses only the isolated `bdc_test_*` scoring tables.
- Does not change scoring calculations, judges, competitors, marks, results, points, or database tables.
- Live scoring code and Live data are unchanged.

## Validation

- Production and Staging health endpoints confirmed dev194 was deployed successfully.
- Public diagnostics confirmed the clean route reaches authentication normally.
- The remaining 500 was isolated to authenticated rendering through the dashboard output wrapper.
- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev195 to Staging through Release Manager.
- Production was not deployed or modified.
