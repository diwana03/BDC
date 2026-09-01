# BDC v2.3.3-dev543

## Fix

- Fixes `Confirm Roster & Start Scoring` on the Dance Cup Automatic setup page. A valid roster confirmation now returns to the unified page at the Judge Scoring section instead of reloading at the roster with only a generic saved message.
- Preserves the specific `Roster confirmed. Judge scoring is ready.` confirmation after redirect.
- Uses the same shared handler and page for isolated Test data and Live data.

## Validation

- PHP syntax check: not run because PHP is unavailable in this workspace.
- Focused Automatic Dance Cup workflow regression: passed.
- `VERSION.json` parse and diff whitespace checks: passed.
- Full static test suite: 123 passed and 28 failed. The unchanged `develop` baseline also has 28 failures, so this fix introduced no additional failing-test count; candidate push remains blocked by the repository release gate.
- Database migration: none.

## Parity Gate

- Testing Score Dashboard: shared `automatic-setup.php` handler verified statically with `data_mode=test` preserved in the redirect.
- Live Scoring Dashboard: shared `automatic-setup.php` handler verified statically with the Live route preserved.
- Projector: not changed; projection state, feed, controls and reveal behavior are unaffected.
- Staging runtime: pending deployment of this exact candidate.
- Production promotion: blocked until the Staging runtime check passes.

## Deployment Status

- Candidate only. Not deployed to Staging or Production.
