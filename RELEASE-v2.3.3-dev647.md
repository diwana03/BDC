# BDC v2.3.3-dev647 — Readable live-score identities

## Changes

- Removes tiny emoji country codes such as **JP**, **TH** and **SG** from before contestant names on Live Contestant Scores.
- Enlarges contestant names while preserving a single-line responsive layout.
- Displays real national flag images immediately after each contestant name.
- Supports every configured country flag for multi-country contestant profiles.

## Validation

- Candidate/static: focused live-score identity integration checks, version JSON, the full projector JavaScript suite and repository whitespace checks passed. PHP syntax execution was unavailable because this workspace has no PHP runtime.
- Staging/runtime: Not Runtime-Tested. Deploy this exact `develop` commit to Staging and inspect every Live Contestant Scores page at 16:9 before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Live Contestant Scores query and renderer inspected; no scoring calculation change.
- Live Scoring Dashboard: shared Live Contestant Scores query and renderer inspected; no scoring calculation change.
- Live Scoreboard/projector: name sizing, single-line protection, real flag placement, multi-country rendering and pagination integration statically verified.

## Migration

- No database migration.
