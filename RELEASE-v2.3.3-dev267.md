# BDC 2.3.3-dev267

## Comment-level ranking reminder

- Removes the duplicated Top-N sentence from the sticky ranking toolbar.
- Keeps the full Final instruction once at the top of the scoring page.
- Places a compact dynamic reminder beside every `Comment · private draft` label.
- On narrow mobile screens, the reminder wraps below the Comment label for readability.

## Parity Gate

- **Testing Score Dashboard:** criteria-acceptance instruction remains aligned with Live; no scoring behavior changed.
- **Live Scoring Dashboard:** comment labels now carry the compact Top-N reminder and the toolbar duplicate is removed.
- **Live Scoreboard / projector:** unchanged; polling and 429 protections remain active.
- **Candidate/static gate:** JSON validation and `git diff --check` passed; mobile wrapping and dynamic rank values were statically checked.
- **Staging/runtime gate:** pending deployment and mobile verification. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
