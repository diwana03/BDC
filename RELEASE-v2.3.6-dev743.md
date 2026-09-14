# BDC 2.3.6-dev743

## Council-standard detailed Final result

- Uses the established public title pattern: event name followed by the council level, for example `SBE 2026 Salsa Bachata Experience - BDC INTERMEDIATE`.
- Displays `BDC INTERMEDIATE`, `BDC OPEN` and `BDC INVITATIONAL` for Bachata reports.
- Displays the corresponding `SDC INTERMEDIATE`, `SDC OPEN` and `SDC INVITATIONAL` levels for Salsa reports.
- Keeps the complete official Final record: placements, both finalist names and bibs, every judge rank, Relative Placement counts and decision trail, Chief Judge, judge key, witnesses and print output.
- Applies the same format to readable pages and `Landscape, All Judges`.
- Feeds directly into the complete immutable special-category archive introduced in dev742.

## Parity Gate

- Testing Score Dashboard — PASS: `admin/scoring-tests/final-result.php` uses the shared council-level formatter and retains the complete Final evidence.
- Live Scoring Dashboard — PASS: `admin/scoring/final-result.php` has the identical report structure and formatter.
- Result archive — PASS: `admin/scoring/special-publish.php` captures both detailed report layouts without rebuilding a generic result table.
- Projector — PASS, unaffected: no projection feed, control or renderer changed.
- Staging/runtime — NOT RUNTIME-TESTED: deploy the exact candidate to Staging, open a BDC Intermediate Final in both layouts, refresh a published special Final and compare the stored report fields.
- Production — BLOCKED until the exact Staging candidate passes runtime verification.

## Validation and deployment

- Focused dev743 report-format regression: PASS.
- JavaScript/static suite: 274 PASS; 2 environment-blocked wrappers require the unavailable PHP CLI.
- PHP syntax/runtime and Staging browser workflow: NOT RUNTIME-TESTED in this workspace.
- Database migration: none.
- Deployment status: workspace candidate only; Production untouched.
