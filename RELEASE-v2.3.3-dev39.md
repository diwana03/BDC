# BDC v2.3.3-dev39

## Release Manager Exact-Staging Safety Fix

- Fixes the false `Tested on Staging` state reported for a release that was never deployed to BDC_STAGING.
- A release candidate may be marked `passed` or `approved` only when a successful Staging deployment job exists for the same release candidate and the exact same Git commit SHA.
- `staging_tested_sha` can no longer be populated merely because a version string matches the currently installed Staging version.
- The migration automatically resets stale `passed` or `approved` candidates to `new` when no exact successful Staging deployment record exists.
- Production remains locked until the exact candidate commit has a successful Staging deployment record.
- Legitimate queued direct-CLI Staging reconciliation remains supported because the reconciliation records the matching Staging job as successful before the candidate can pass.

## Required Safety Sequence

1. Push release to GitHub `develop`.
2. Release appears as Available / Not Tested.
3. Explicitly deploy that exact commit to BDC_STAGING.
4. Successful Staging deployment and health check record the exact SHA.
5. Only then may the release show `Tested on Staging` and unlock Production.

## Safety

- No deployment to Staging is performed by this development release.
- No deployment to Production is performed.
- `config/config.php` is untouched.
- No scoring, competitor, points or registration logic is changed.
