# BDC v2.3.3-dev644 — Judge identity alignment

## Changes

- Centres the judge name, assignment scope, flag and country on the same vertical axis in the right identity column.
- Makes a single-country entry occupy the full identity-column width so its flag cannot remain left-offset.
- Preserves multi-country wrapping, one-line judge names, larger flags and the approved 4 + 4 + 3 card layout.

## Validation

- Candidate/static: focused judge-axis alignment, one-line name, flag sizing, cache integration, version JSON, full projector JavaScript suite and repository whitespace checks passed.
- Staging/runtime: Not Runtime-Tested. Deploy this exact `develop` commit to Staging and inspect the full Judges board and Call Judge screen before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared projector renderer inspected; no scoring workflow change.
- Live Scoring Dashboard: shared projector renderer inspected; no scoring workflow change.
- Live Scoreboard/projector: shared judge-card stylesheet and cache integration statically verified.

## Migration

- No database migration.
