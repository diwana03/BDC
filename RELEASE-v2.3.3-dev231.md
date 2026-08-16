# BDC 2.3.3-dev231

## Automatic scoring bootstrap recovery

- Prevents the `Cannot redeclare e()` fatal error from stopping Automatic Scoring.
- Keeps the early bootstrap guard and adds defensive helper-level protection for mixed or stale server entry paths.
- Uses one-time bootstrap loading for the Test Automatic screen, inline scoring panel and live-data endpoint.
- Preserves the dev230 repair for existing Automatic scores, drafts, Calculate & Sort and Submit Scores.
- Applies shared bootstrap protection to both Testing and Live without changing scoring calculations.

## Validation and deployment

- Static bootstrap and Automatic Scoring entry-path validation completed.
- PHP runtime validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
