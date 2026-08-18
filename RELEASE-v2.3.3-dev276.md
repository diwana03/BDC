# BDC 2.3.3-dev276 · Build 2982

## Bachata Rising category correction

- Fixes the isolated Test Event generator silently converting Bachata Rising to Bachata Novice.
- Preserves every valid special-category identifier selected by the scorer.
- Rejects an invalid category explicitly instead of creating a misleading Novice round.
- Confirms the Live workflow already routes and persists Bachata Rising through the dedicated special-category creator.

## Parity Gate

- **Testing Score Dashboard:** `generate_test_event` now accepts `bachata_rising` and all registered special categories without falling back to Novice.
- **Live Scoring Dashboard:** dedicated special-category routing and exact category persistence statically verified; no Live behavior change was required.
- **Projector / Live Scoreboard:** not data-mutating; it reads the saved round division, so no projector source change was required.

## Validation

- Candidate/static: JSON validation, whitespace validation, Test regression-source inspection, Live route inspection, and exact special-category persistence checks completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
