# BDC 2.3.3-dev181

## Summary

- Adds an early Live scoring loader guard so a fatal error in the shared scoring page produces a traceable recovery screen instead of an empty HTTP 500.
- Adds **Edit Draft Details** to both Testing and Live Saved Rounds.
- Allows event name, event date, dance style, and division changes only while the draft has no saved heats or final judge marks.
- Uses one shared editor service and one shared form so Testing and Live validation remains aligned.

## Validation

- Confirmed the Testing and Live dashboards both count heats and final marks before showing the edit action.
- Confirmed the shared service repeats the scoring lock server-side, including direct URL and POST attempts.
- Confirmed duplicate Event + Dance + Division workflows are rejected.
- Confirmed event name/date changes are rejected when another round for that event already contains scores.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is not available in this workspace, so server-side PHP execution remains part of Staging validation.

## Database migrations

- No migration added.
- No destructive database operation.

## Deployment

- Source release only. The user deploys `develop` to Staging through Release Manager.
- Production deployment is not performed by the coding agent.
