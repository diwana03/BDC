# BDC 2.3.3-dev257

## Mobile Final judge ranking

- Replaces the Final rank dropdown on the automatic judge screen with large one-tap rank buttons.
- Shows the configured ranking range, such as 1–10 for a Top 10 Final.
- Turns the selected rank green and greys out that rank for every other couple.
- Adds a visible NO RANK selection for couples outside the required Top N.
- Keeps large leader/follower bib numbers above names for faster judging.
- Tightens mobile card spacing and uses five rank buttons per row.
- Collapses the private device-only draft-ranking scratchpad to preserve screen space.
- Retains autosave plus client-side and server-side duplicate-rank protection.

## Parity Gate

- **Testing Score Dashboard:** configured Top-N depth, generated test rankings and Final validation remain unchanged and use the shared Top-N calculation rules.
- **Live Scoring Dashboard:** the Final judge link saves the same rank values as before; only the mobile input presentation changed.
- **Live Scoreboard / projector:** no scoring payload or rendering contract changed.
- **Candidate/static gate:** `git diff --check` passed; rank availability, NO RANK clearing and submit-readiness paths were statically checked.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until mobile Staging verification passes.

## Migration and deployment

- Database migration: none.
- Deployment: source candidate only. No Production changes were made.
