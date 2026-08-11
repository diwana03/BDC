# BDC v2.3.3-dev42

## Special Category Scoring 500 Fix

- Fixes the HTTP 500 regression when opening an existing special-category scoring round such as `?mode=special&round_id=9`.
- The special scoring wrapper now loads the shared portal bootstrap with `require_once` before embedding the standard manual scoring core.
- This prevents bootstrap/session/helper initialization from being executed twice when `special.php` includes `core.php`.
- Special-category scoring remains Manual Scoring with Relative Placement in this release.
- Fixed special-category points and role-specific Novice / Intermediate / Advanced point-bucket rules are unchanged.
- BDC ID assignment is unchanged.

## Safety

- No database migration.
- No Production changes.
- No server deployment included.
