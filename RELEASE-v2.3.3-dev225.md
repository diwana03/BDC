# BDC 2.3.3-dev225

## Scoring parity baseline

- Added a release-blocking static parity test for the Testing Score Dashboard, Live Scoring Dashboard, and shared public projector.
- Added the permanent capability matrix in `docs/SCORING-PARITY.md`.
- Live automatic scoring now displays `LATER` consistently with Test.
- Test score reports now format automatic judge values and the Average heading consistently with Live.
- Winner podium country flags now render once per contestant, including same-country couples.

## Parity Gate

### Testing Score Dashboard

- `admin/scoring-tests/index.php`
- `admin/scoring-tests/automatic-inline.php`
- `admin/scoring-tests/automatic-live-data.php`
- `admin/scoring-tests/result.php`

### Live Scoring Dashboard

- `admin/scoring/core.php`
- `admin/scoring/automatic-common-setup.php`
- `admin/scoring/automatic-round.php`
- `admin/scoring/automatic-live-data.php`
- `admin/scoring/result.php`

### Live Scoreboard / projector

- `admin/live-screen/control.php`
- `live-display/feed.php`
- `live-display/state.php`

### Shared and intentionally Test-only behavior

- Public projector feed and controls are shared and select `bdc_*` or `bdc_test_*` tables by data mode.
- Random test generators, isolated reset tools, and `bdc_test_*` fixtures remain intentionally Test-only.

## Validation

- Candidate/static parity: pending final release checks.
- PHP runtime tests: run after this exact candidate is deployed to Staging because the current workspace does not provide PHP.
- Staging/browser parity: required before Production approval.

## Migration and deployment

- No database migration.
- Push target: `develop` only.
- Production is not modified.
