# BDC 2.3.3-dev259

## Optional used-rank guidance

- Adds a sticky Grey Out Used Ranks button above the Final couple cards.
- When enabled, visually dims placements already assigned to another couple.
- Lists the placements still available, such as `Available: 4, 7, 9`.
- Changes the control to Show All Ranks when highlighting is active.
- Keeps dimmed ranks clickable so judges can still move an existing rank through the confirmation flow.
- Remembers each judge's display preference on that device.

## Parity Gate

- **Testing Score Dashboard:** Top-N settings and shared Final validation remain unchanged.
- **Live Scoring Dashboard:** only optional visual guidance on the automatic Final judge screen changed; official rank persistence is unchanged.
- **Live Scoreboard / projector:** no payload or rendering contract changed.
- **Candidate/static gate:** `git diff --check` passed; used-rank detection, available-rank summary, toggle persistence and clickable reassignment paths were statically checked.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until mobile Staging verification passes.

## Migration and deployment

- Database migration: none.
- Deployment: source candidate only. No Production changes were made.
