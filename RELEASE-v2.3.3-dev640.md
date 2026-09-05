# BDC v2.3.3-dev640 — Projector roster layout repair

## Changes

- Restores the approved competitor card order: BIB on the left, portrait in the centre, and name plus flag and full country on the right.
- Prevents competitor flags and country labels from overlapping the portrait.
- Makes the eleven-judge board use a centred four, four, three arrangement without shrinking the final row.
- Prevents single-word judge names from being split in the middle.
- Preserves fifteen Leaders and fifteen Followers per page, Chief Judge highlighting, projector safe margins and one-by-one judge calls.

## Validation

- Candidate/static: focused roster layout assertions, all projector/projection JavaScript tests, version JSON and repository whitespace checks.
- Staging/runtime: Not Runtime-Tested. Deploy this exact commit to Staging and inspect Competitors, Judges and one-by-one Judge Call at 16:9 before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Testing projector feed path checked statically.
- Live Scoring Dashboard: shared Live projector feed path checked statically.
- Live Scoreboard/projector: competitor grid, judges grid, cache version and one-judge selector compatibility checked statically.

## Migration

- No database migration.
