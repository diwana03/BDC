# BDC 2.3.3-dev262

## Portal request-storm protection

- Reduces the public projector state poll from 700 ms to 2.5 seconds.
- Reduces submitted-judge and judge-control polling to safe intervals.
- Reduces Registration Desk synchronization frequency and blocks overlapping requests.
- Pauses network polling while browser tabs are hidden.
- Handles HTTP 429 responses without repeatedly parsing or reloading failed responses.
- Keeps active scoring inputs, autosave, calculations and submissions unchanged.

## Parity Gate

- **Testing Score Dashboard:** submitted Test judge polling is throttled with the same hidden-tab and 429 protection as Live.
- **Live Scoring Dashboard:** submitted judge, judge-control and Registration Desk polling are throttled without changing scoring persistence or workflow.
- **Live Scoreboard / projector:** live state polling remains automatic but is reduced to a safe 2.5-second interval and pauses in hidden tabs.
- **Candidate/static gate:** `git diff --check` passed; polling intervals, hidden-tab guards, overlap guards and 429 branches were statically checked.
- **Staging/runtime gate:** pending recovery of the host-level 429 block and deployment of this exact candidate. Production promotion remains blocked until sustained multi-tab Staging verification passes.

## Migration and deployment

- Database migration: none.
- Deployment: emergency Staging candidate only. No Production changes were made directly.
