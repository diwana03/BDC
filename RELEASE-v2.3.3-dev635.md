# BDC v2.3.3-dev635 — Projector identity alignment and silent refresh

## Changes

- Keeps the approved BIB or judge assignment on the left and portrait in the centre.
- Aligns up to five flags in the right identity column, followed by the competitor or judge name and then the complete country names.
- Removes ellipsis and mid-word splitting from the active competitor and judge country presentation.
- Keeps the existing projector visible while the replacement stylesheet, flags and photos load, then performs one short crossfade without a full-page reload.

## Validation

- Candidate/static: inline projector JavaScript syntax, focused projector integration tests, JSON parsing and repository whitespace checks. PHP CLI is unavailable in this workspace; no PHP-language block was changed in this release.
- Staging/runtime: Not Runtime-Tested. Production promotion remains blocked until this exact commit is deployed and visually checked on the Test and Live projector screens.

## Parity Gate

- Testing Score Dashboard: shared projector state, round and roster path checked statically.
- Live Scoring Dashboard: same shared projector state, round and roster path checked statically.
- Live Scoreboard/projector: `live-display/index.php`, `live-display/feed.php` and `public/css/projector-roster-v615.css` checked statically.

## Migration

- No database migration.
