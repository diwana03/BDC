# BDC 2.3.3-dev261

## Emergency rollback

- Rolls back the Final judge instruction change introduced in build 2966.
- Restores the exact judge-scoring page content from the last known build.
- Contains no database, scoring-calculation, workflow, projector, or permission changes.

## Parity Gate

- **Testing Score Dashboard:** unchanged; isolated `bdc_test_*` scoring workflow remains at the previous release state.
- **Live Scoring Dashboard:** restores the prior Automatic Final judge instruction; ranking controls and persistence are unchanged.
- **Live Scoreboard / projector:** unchanged.
- **Candidate/static gate:** `git diff --check` passed and the rollback was compared against the prior judge-scoring file.
- **Staging/runtime gate:** pending deployment by the user. Production promotion remains blocked until portal health is confirmed.

## Migration and deployment

- Database migration: none.
- Deployment: emergency source rollback candidate only. No Production changes were made directly.
