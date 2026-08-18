# BDC 2.3.3-dev281 · Build 2987

## Final duplicate-rank replacement

- When a judge chooses a placement already assigned to another couple, the selected couple receives that placement.
- The previously assigned couple is explicitly changed to **NO RANK**; placements are never swapped.
- The confirmation names both affected pairs and explains the exact result before saving.
- The interface immediately refreshes both cards and confirms which pair became **NO RANK**.

## Parity Gate

- **Testing Score Dashboard:** shared Automatic Final judge-link workflow and duplicate-placement handling checked.
- **Live Scoring Dashboard:** shared Automatic Final judge-link workflow, AJAX persistence, completion validation, and submission locking checked.
- **Projector / Live Scoreboard:** unaffected; draft judge placements remain private and are not projected.

## Validation

- Candidate/static: JSON validation, whitespace validation, duplicate-placement regression assertions, and shared Test/Live judge-screen parity completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
