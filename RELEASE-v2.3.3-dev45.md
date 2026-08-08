# BDC v2.3.3-dev45

## Release Manager State Fix

- Removes the legacy `bdc_release_exact_staging_guard` database trigger introduced by the earlier worker-only deployment model.
- Fixes the repeated state where the same version currently running on Staging appeared again as `Available / Deploy to Staging`.
- Allows the current Staging version reconciliation logic to set the matching release candidate to the Staging-passed state instead of being silently reset to `new` by the old trigger.
- Restores the Production promotion path when Current Staging and the latest release version match.
- Keeps the application-level Production safety check that the selected candidate commit SHA must match `staging_tested_sha`.

## Safety

- Forward migration only drops the obsolete trigger and clears obsolete `current_staging` transitional markers.
- No scoring, competitor, repository, or BDC ID logic is changed.
