# BDC 2.3.3-dev269

## Premium Final ranking palette

- Replaces the basic pale buttons with rich jewel-tone gradients.
- Gives ranks 1–3 gold, platinum and bronze finishes.
- Uses a coordinated ocean, emerald, indigo and violet palette for ranks 4–8.
- Uses refined steel and midnight finishes for ranks 9–10.
- Adds depth, soft shadows, hover movement and a selected-state glow/checkmark.
- Gives NO RANK a distinct crimson gradient and selected-state emphasis.

## Parity Gate

- **Testing Score Dashboard:** Final Top-N configuration and validation remain unchanged.
- **Live Scoring Dashboard:** only Final judge presentation changed; rank selection, comments, autosave and duplicate handling remain unchanged.
- **Live Scoreboard / projector:** unchanged; polling and 429 protections remain active.
- **Candidate/static gate:** JSON validation and `git diff --check` passed; all rank, selected, used-rank and NO RANK visual states were statically checked.
- **Staging/runtime gate:** pending deployment and mobile contrast/tap verification. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
