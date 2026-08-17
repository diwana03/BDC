# BDC 2.3.3-dev266

## Pre-scoring criteria instruction

- Updates the criteria-acceptance screen displayed before a judge can start scoring.
- Shows the configured Final ranking depth dynamically.
- Uses: `Rank your TOP 10 best couples from 1 to 10. Use each rank once. Leave all other couples as NO RANK.`
- Applies the same instruction to Test and Live criteria-acceptance screens.
- Keeps a compact dynamic reminder in the sticky toolbar above the Final ranking buttons.

## Parity Gate

- **Testing Score Dashboard:** Test judge criteria acceptance uses the Test round's callback/ranking depth.
- **Live Scoring Dashboard:** Live judge criteria acceptance uses the Live Final's configured callback/ranking depth.
- **Live Scoreboard / projector:** unchanged; request-storm and 429 protections remain active.
- **Candidate/static gate:** JSON validation and `git diff --check` passed; Test and Live text and dynamic values were statically checked.
- **Staging/runtime gate:** pending deployment and verification with a fresh judge session that has not accepted criteria. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
