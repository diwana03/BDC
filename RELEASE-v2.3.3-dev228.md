# BDC 2.3.3-dev228

## Automatic calculation failure boundary

- Automatic scoring POST failures now return to `automatic-round.php` instead of opening the generic scoring failure page.
- The signed-in administrator receives the exact safe exception message through the existing one-time Automatic dashboard alert.
- Saved marks and round data remain unchanged when calculation fails.

## Scope and validation

- Changed only the live Automatic routing boundary in `admin/scoring/index.php` plus release metadata.
- Manual scoring, Test Scoring and projector rendering are unchanged.
- No database migration.
- Push target: `develop` only; Production is not modified.
