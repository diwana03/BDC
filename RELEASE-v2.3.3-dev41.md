# BDC v2.3.3-dev41

## Release Manager Staging Proof Fix

- Fresh GitHub releases must remain Available until they are actually deployed to Staging.
- "Tested on Staging" now requires a successful Staging deployment job for the same release candidate and exact commit SHA.
- The successful job must come from the normal deployment worker path.
- The historical direct-CLI reconciliation marker is not accepted as proof of a real Staging deployment.
- Existing false `passed` or `approved` candidate state without valid worker-backed proof is reset to `new`.
- `staging_tested_sha`, approval timestamps and approval identity are cleared when the proof is invalid.
- Production therefore remains locked until the exact release has genuinely passed Staging through the normal deployment workflow.

## Expected Release Flow

1. Push new version to GitHub `develop`.
2. Release Manager shows Available / Deploy to Staging.
3. A user explicitly starts the Staging deployment.
4. The exact commit is deployed and the health check succeeds.
5. Release Manager records Tested on Staging.
6. Production promotion becomes available for that exact commit only.

## Safety

- No automatic Staging deployment is introduced.
- No Production deployment is introduced.
- `config/config.php` is untouched.
- No scoring, competitor, points or repository data logic is changed by this release.
