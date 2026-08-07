# BDC v2.3.3-dev43

## Release Manager State Model Fix

- Separates four concepts that were previously being conflated: latest GitHub release, physically installed Staging build, worker-validated Staging build, and Production-eligible build.
- Adds an explicit `current_staging` state for a build that is physically running on BDC_STAGING without valid worker-backed deployment proof.
- A physically installed Staging build is no longer presented as `Available / Deploy to Staging` simply because its candidate record is still `new`.
- `current_staging` never sets `staging_tested_sha` and never unlocks Production.
- Genuine worker-backed Staging deployments still become `passed` after the exact commit is deployed and health checks succeed.
- Existing `passed`, `approved`, queued and testing states are preserved and are not overwritten by installed-version detection.
- When Staging moves to a different build, the old `current_staging` marker is returned to `new`.
- Exact commit SHA is preferred whenever an installed release manifest is available. Version matching is used only as a display/state fallback and is never accepted as Production proof.

## Database

- Release candidate `status` is widened to `VARCHAR(40)` so `current_staging` can be represented safely without weakening the existing exact-Staging proof trigger.

## Safety

- Production remains locked behind the existing exact worker-backed Staging proof rules.
- No scoring, competitor, BDC ID or point logic changes.
- No server deployment included.
