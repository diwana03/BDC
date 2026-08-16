# BDC 2.3.3-dev229

## Automatic fatal-error recovery

- Extends the Automatic scoring action boundary to PHP fatal errors as well as exceptions.
- Calculate & Sort and Submit Scores return to the Automatic dashboard with the concrete server failure message.
- The shutdown reserve is released before recording and displaying the failure, including memory-related failures.
- Saved scores and round data are not cleared.

## Scope

- Live Automatic routing guard only; Manual, Test and projector behavior are unchanged.
- No database migration.
- Push target: `develop` only; Production is not modified.
