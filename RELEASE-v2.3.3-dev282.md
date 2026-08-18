# BDC 2.3.3-dev282 · Build 2988

## Automatic Test Judge Live Scoring routing

- Fixes the embedded **Judge Live Scoring returned HTTP 404** failure on Automatic Test rounds.
- Resolves the panel from the active scoring-test directory first, making the page safe across Staging and other installation subfolders.
- Retains the configured application URL as a fallback while limiting fallback behaviour to genuine HTTP 404 responses.
- Makes panel refresh and panel actions self-relative so regenerate, reopen, delete, simulation, and clearing controls remain on the same deployed environment.

## Parity Gate

- **Testing Score Dashboard:** Automatic Test wrapper, embedded judge panel, refresh loop, and panel POST actions checked.
- **Live Scoring Dashboard:** unchanged; its direct Automatic scoring route and judge controls remain intact.
- **Projector / Live Scoreboard:** unaffected; no projection route or display state changed.

## Validation

- Candidate/static: JSON validation, whitespace validation, Automatic Test routing regression checks, and Test/Live route separation completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
