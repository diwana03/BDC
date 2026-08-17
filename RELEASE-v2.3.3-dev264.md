# BDC 2.3.3-dev264

## Final judge guidance and 429 resilience

- Adds concise mobile-friendly Final guidance based on the configured Top-N depth.
- Example: `Choose your TOP 10. Use each place 1–10 once. Set all other couples to NO RANK.`
- Adds a one-minute projector polling backoff whenever the host returns HTTP 429.
- Retains hidden-tab suspension, slower polling and overlap protection from dev262.
- Does not modify the restored scoring core file.

## Parity Gate

- **Testing Score Dashboard:** Test polling protection remains active; scoring setup, validation and persistence are unchanged.
- **Live Scoring Dashboard:** only the dynamic Final judge instruction changed; all Top-N controls, comments, validation and autosave remain unchanged.
- **Live Scoreboard / projector:** adds host-aware 429 backoff while preserving automatic display updates.
- **Candidate/static gate:** JSON validation, JavaScript syntax check and `git diff --check` passed; large-file integrity was verified before publishing.
- **Staging/runtime gate:** pending deployment of this exact candidate and mobile/multi-tab verification. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
