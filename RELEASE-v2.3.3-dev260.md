# BDC 2.3.3-dev260

## Mobile Final ranking guidance

- Replaces the long Final judge instruction with concise mobile-friendly wording.
- Shows the Final's configured ranking depth dynamically, for example: `Choose your TOP 10. Use each place 1–10 once. Set all other couples to NO RANK.`
- Keeps the instruction directly above the couple comment and ranking cards.
- Makes the required treatment of every non-selected couple explicit.

## Parity Gate

- **Testing Score Dashboard:** Final Top-N configuration and isolated `bdc_test_*` validation were checked; no calculation, persistence, or Testing workflow changed.
- **Live Scoring Dashboard:** the Automatic Final judge instruction was updated; its dynamic rank limit, rank controls, comments, autosave, duplicate protection, and submission behavior remain unchanged.
- **Live Scoreboard / projector:** no payload, score, competitor, or rendering contract changed.
- **Candidate/static gate:** `git diff --check` passed; dynamic Top-N output and the surrounding Final judge markup were statically checked.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until mobile Staging verification passes.

## Migration and deployment

- Database migration: none.
- Deployment: source candidate only. No Production changes were made.
