# BDC v2.3.3-dev646 — Clear judge assignment wording

## Changes

- Replaces the ambiguous **Leaders Only** label with **JUDGING LEADERS** on full judge boards and individual judge calls.
- Replaces **Followers Only** with **JUDGING FOLLOWERS**.
- Replaces **Leaders & Followers** with **JUDGING LEADERS & FOLLOWERS**.
- Keeps the audience projector and mobile projection state on the same wording.

## Validation

- Candidate/static: focused judge-scope integration checks, version JSON, the full projector JavaScript suite and repository whitespace checks passed. PHP syntax execution was unavailable because this workspace has no PHP runtime; both PHP edits are limited to string-label substitutions.
- Staging/runtime: Not Runtime-Tested. Deploy this exact `develop` commit to Staging and verify the Judges and Call Judges One by One screens before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared judge-assignment data path inspected; no scoring workflow change.
- Live Scoring Dashboard: shared judge-assignment data path inspected; no scoring workflow change.
- Live Scoreboard/projector: full judge board, individual judge call and mobile projection state wording statically verified.

## Migration

- No database migration.
