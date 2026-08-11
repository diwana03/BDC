# BDC v2.3.3-dev60

## Automatic Scoring 500 Fix

- Fixes the HTTP 500 introduced on Automatic round URLs in dev59.
- Removes duplicate application bootstrap from `admin/scoring/index.php` before `core.php` initialisation.
- Automatic shared Heats UI is now rendered only after the core application has initialised.
- If the Automatic UI enhancement itself fails, the core scoring page remains available instead of returning a fatal error.
- No scoring, registration, points or Release Manager logic changed in this hotfix.
