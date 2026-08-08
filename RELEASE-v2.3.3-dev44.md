# BDC v2.3.3-dev44

## Release Manager Workflow Repair

- If the version actually running on BDC_STAGING matches a discovered release candidate, the Release Manager no longer offers that same version for another Staging deployment.
- The matching current Staging candidate is reconciled to the existing Production promotion path.
- If `storage/release.json` contains a commit SHA, the exact SHA is used. Older cron/direct Staging installs that only expose `VERSION.json` use the newest candidate with the exact installed version.
- A GitHub push alone does not change release state unless the Staging application itself reports that version as installed.
- Production still requires `staging_tested_sha` to equal the selected release commit SHA and the normal health/preflight checks still run.
- The obsolete `current_staging` intermediate state and the overly strict worker-only database trigger are removed.

## Other behavior

- No scoring, competitor, BDC ID, results or points logic changed.
