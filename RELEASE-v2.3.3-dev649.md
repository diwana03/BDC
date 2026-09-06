# BDC v2.3.3-dev649 — Final projector roster and couple layout

Build 3355

## Changes

- Adds a dedicated Finalists screen before Finalist Couples in Final projection controls.
- Finalists reads active Final round entries and reuses the existing split Leader / Follower roster cards.
- Finalist Couples stays paired, with equal five-column card widths and centred incomplete final rows.
- Tightens only Finalist Couple card internals so BIBs remain fully visible.
- Preserves the existing logo/header gap, judges, scoring, relative placement, matching, podium and results logic.
- Bumps the projector safe stylesheet cache.

## Validation

- PHP 8.1 syntax checks on changed PHP.
- Focused Finalists projector regression.
- Existing judge-country projector runtime regression.
- No database migration.

## Known baseline

The repository-wide legacy projector regression gate was already red on the unchanged dev648 baseline because older tests hard-code obsolete projector cache keys and formatting. This release does not rewrite those unrelated legacy tests.

## Deployment

Candidate: `develop`. Production remains blocked until exact Staging runtime verification.
