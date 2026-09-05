# BDC v2.3.3-dev639 — Projector round selection recovery

## Changes

- Fixes the blank projector error `Selected round is not available for this Live Display` when a valid selected round belongs to a member event in a shared or festival projector session.
- Derives `active_event_id` from the selected round instead of incorrectly reusing the session's original event.
- Validates the selected round's event against the projector session membership before any session update.
- Adds a read-only renderer fallback that immediately accepts an already-selected valid member round, allowing currently affected displays to recover after deployment.
- Does not change competitors, judges, marks, results, links, tokens or round assignments.

## Validation

- Candidate/static: shared projector regression tests, focused selected-round event integrity checks, JSON parsing and repository whitespace checks.
- Staging/runtime: Not Runtime-Tested. The exact commit must be deployed to Staging and checked with an ordinary event plus a multi-event festival session before Production promotion.

## Parity Gate

- Testing Score Dashboard: isolated Test session and round-table selection path checked statically.
- Live Scoring Dashboard: Live session and round-table selection path checked statically.
- Live Scoreboard/projector: session membership validation, active-event derivation and renderer recovery checked statically.

## Migration

- No database migration.
