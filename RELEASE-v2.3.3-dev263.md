# BDC 2.3.3-dev263

## Emergency file-integrity repair

- Restores the complete `admin/scoring/core.php` file after the dev262 repository publish transferred an incomplete blob.
- Retains the request-storm protection added to projector, judge, score-matrix and Registration Desk polling surfaces.
- Makes no database, scoring-calculation, permission or Production changes.

## Parity Gate

- **Testing Score Dashboard:** dev262 Test polling protection retained; core Test/Live dashboard file restored byte-for-byte from dev261.
- **Live Scoring Dashboard:** complete core dashboard restored; separate polling protection files retained.
- **Live Scoreboard / projector:** dev262 projector throttling retained unchanged.
- **Candidate/static gate:** `git diff --check` passed and restored core blob identity was verified against dev261.
- **Staging/runtime gate:** requires immediate deployment over dev262 and a new Staging health check. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: emergency Staging repair only. No Production changes were made directly.
