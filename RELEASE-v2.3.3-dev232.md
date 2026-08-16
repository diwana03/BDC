# BDC 2.3.3-dev232

## Complete bootstrap helper protection

- Protects the remaining global `country_flag_url()` helper against duplicate declaration.
- Completes defensive guards for every global function declared by `bootstrap.php`.
- Prevents Automatic Scoring from stopping when a server request loads bootstrap through overlapping entry paths.
- Preserves the dev230 existing-score calculation repair and dev231 bootstrap protections.
- Does not change marks, drafts, callbacks, results or calculation rules.

## Validation and deployment

- All global bootstrap declarations inspected and protected.
- PHP runtime validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
