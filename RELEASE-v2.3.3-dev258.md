# BDC 2.3.3-dev258

## Faster Final judge drafting and reranking

- Replaces the large shared draft scratchpad with a compact Comment field below every Final couple.
- Stores each judge comment privately on that device and keeps it separate from official scores.
- Keeps every Final rank button available instead of greying out ranks used elsewhere.
- When a used rank is selected, asks whether to move it from the previous couple to the current couple.
- Confirming the move clears the old assignment first and then saves the new assignment.
- Cancelling preserves the existing ranking.
- Retains NO RANK, autosave, submit-readiness checks and server-side duplicate protection.

## Parity Gate

- **Testing Score Dashboard:** Top-N settings, generated Final rankings and shared Relative Placement validation remain unchanged.
- **Live Scoring Dashboard:** only the Final judge drafting and rank reassignment interface changed; saved rank values use the existing API.
- **Live Scoreboard / projector:** no payload or rendering contract changed.
- **Candidate/static gate:** `git diff --check` passed; comment persistence, move confirmation, old-rank clearing and new-rank saving paths were statically checked.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until mobile Staging verification passes.

## Migration and deployment

- Database migration: none. Comments use device-local browser storage and are not official score records.
- Deployment: source candidate only. No Production changes were made.
