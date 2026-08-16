# BDC 2.3.3-dev227

## Automatic Calculate & Sort repair

- Live Automatic scoring actions now follow Post/Redirect/Get after processing.
- Calculate & Sort, Submit Scores, Save Draft and callback-tie resolution return to `automatic-round.php` instead of falling through to the legacy dashboard renderer.
- Success notices and validation errors are preserved in the Automatic dashboard session.
- Saved marks and calculated results remain server-authoritative and unchanged by the redirect.

## Parity Gate

- Test Automatic workflow: `admin/scoring-tests/automatic-inline.php` and `admin/scoring-tests/index.php`.
- Live Automatic workflow: `admin/scoring/automatic-round.php`, `admin/scoring/index.php` and `admin/scoring/core.php`.
- Projector: shared `live-display/feed.php` and `live-display/state.php`; calculation results continue to refresh through the shared data-version feed.
- Static gate: Automatic controls, action handlers, notices, 303 return path, and shared projector refresh markers checked together.
- Staging/runtime gate: repeat Calculate & Sort and Submit Scores after deploying this exact candidate.

## Migration and deployment

- No database migration.
- Push target: `develop` only.
- Production is not modified.
