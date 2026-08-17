# BDC 2.3.3-dev247

## Automatic completed-round override

- Displays the `RESUBMIT` confirmation field directly on a completed Live Automatic Scoring round.
- Allows Scorer, Master Scorer and Super Admin to unlock without leaving the current automatic page.
- Keeps all other completed-round controls disabled until the override succeeds.
- Returns the successful override to the same Automatic Scoring round.
- Displays the resulting success or validation error on that round.
- Keeps the existing audit record and preserves linked child rounds.
- Test Automatic already uses the shared completed-round override and remains covered.

## Scope

- Test and Live workflow parity verified.
- No database migration.
- No Production deployment.
