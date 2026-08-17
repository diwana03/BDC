# BDC 2.3.3-dev265

## Final judge instructions

- Adds the configured Top-N instruction to the Final judge View Criteria dialog.
- Repeats the same concise instruction above the Final couple cards.
- Example: `Rank your TOP 10 best couples from 1 to 10. Use each rank once. Leave all other couples as NO RANK.`
- Keeps the number dynamic for every configured Final ranking depth.

## Parity Gate

- **Testing Score Dashboard:** Top-N setup and validation remain unchanged.
- **Live Scoring Dashboard:** Final judge criteria and card instructions now use the same dynamic Top-N wording; scoring behavior is unchanged.
- **Live Scoreboard / projector:** no payload or rendering contract changed; dev264 polling protection remains active.
- **Candidate/static gate:** JSON validation and `git diff --check` passed; both Final instruction locations were checked against the same dynamic rank limit.
- **Staging/runtime gate:** pending deployment of this exact candidate and mobile verification. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
