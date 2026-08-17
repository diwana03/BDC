# BDC 2.3.3-dev268

## Color-coded Final ranking controls

- Removes `private draft` from every Final competitor Comment label.
- Gives ranks 1–10 distinct accessible colors, including gold, silver and bronze for the podium ranks.
- Uses a stronger matching color when a placement is selected.
- Makes NO RANK clearly red, with a solid red selected state.
- Preserves the optional Grey Out Used Ranks behavior and mobile layout.

## Parity Gate

- **Testing Score Dashboard:** Final Top-N configuration and validation remain unchanged.
- **Live Scoring Dashboard:** only the Final judge button presentation and Comment label changed; autosave and ranking behavior are unchanged.
- **Live Scoreboard / projector:** unchanged; polling and 429 protections remain active.
- **Candidate/static gate:** JSON validation and `git diff --check` passed; rank states, used-rank override, NO RANK state and mobile layout were statically checked.
- **Staging/runtime gate:** pending deployment and mobile contrast/tap verification. Production remains blocked.

## Migration and deployment

- Database migration: none.
- Deployment: Staging candidate only. No Production changes were made directly.
