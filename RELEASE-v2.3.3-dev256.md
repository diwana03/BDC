# BDC 2.3.3-dev256

## Inherited-round Registration Desk protection

- Greys out and locks the Registration Desk for Semifinal and Final rounds created from a previous qualification round.
- Removes Copy Link, Open Desk, token regeneration and live-sync statistics from inherited rounds.
- Explains that competitors and bibs are inherited and must be managed from the original Heats registration workflow.
- Keeps Registration Desk fully active for Heats and genuinely direct-entry Finals.

## Parity Gate

- **Testing Score Dashboard:** inherited Test rounds show the same disabled Registration Desk status and explanation.
- **Live Scoring Dashboard:** inherited rounds no longer expose registration links or controls; direct-entry rounds remain unchanged.
- **Live Scoreboard / projector:** no payload, competitor, score or rendering contract changed.
- **Candidate/static gate:** `git diff --check` passed and the parent-round conditions were checked on Test and Live.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until Staging verification passes.

## Migration and deployment

- Database migration: none.
- Deployment: source candidate only. No Production changes were made.
