# BDC v2.3.3-dev214

## Dedicated Automatic setup actions

- Removes Automatic competitor mutations from the legacy scoring-page renderer.
- Adds a small dedicated endpoint for Add Existing, Create Name & Add, bib updates, removals and tier settings.
- Writes or reactivates the scoring entry and returns only a clean HTTP redirect.
- Prevents raw gzip/compressed page bytes from appearing after competitor actions.
- Preserves one BDC competitor identity while validating the selected dance-style role.

## Safety

- Existing competitors, scores, judge assignments and results are preserved.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
