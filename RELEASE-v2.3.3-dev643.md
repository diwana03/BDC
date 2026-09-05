# BDC v2.3.3-dev643 — Projector roster integrity

## Changes

- Corrects Flight Round projection so a selected round containing more than 15 competitors in either role is presented as multiple silent projector pages instead of shrinking every card.
- Keeps the selected scoring round unchanged while the audience display cycles its internal roster page using the configured page delay.
- Enforces one-line competitor and judge names with responsive smaller type for constrained cards.
- Enlarges competitor and judge flags while retaining full country names below them.
- Keeps Test and Live projection on the same shared renderer and state service.

## Validation

- Candidate/static: focused Flight Round page-limit, state-count, silent-cycle, name, flag, cache integration, version JSON, JavaScript syntax, and repository whitespace checks passed.
- Staging/runtime: Not Runtime-Tested. Deploy this exact `develop` commit to Staging and verify Round 2 with 15 Leaders and 19 Followers cycles as Page 1 of 2 / Page 2 of 2 without a flash before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Test flight-assignment table path inspected; no scoring data or assignment mutation.
- Live Scoring Dashboard: shared Live flight-assignment table path inspected; no scoring data or assignment mutation.
- Live Scoreboard/projector: shared feed, state, silent iframe swap, delay timer, responsive roster stylesheet, and 15-per-role slicing statically verified.

## Migration

- No database migration.
