# BDC 2.3.3-dev364

## Direct Final prompt

- Shows **Heats are not required** after a qualifying competitor roster is submitted and locked.
- Gives the organiser a dated **Go Directly to Final** action when both Leader and Follower counts are at or below the active 5, 10 or 15 callback quota.
- Creates the existing callback records internally, then opens the normal Final workflow without requiring judges, empty score sheets or fake Heats marks.
- Keeps Heats mandatory when either role exceeds the quota, including the confirmed 8/7 and 8/6 Tier 1 cases.
- Rejects direct advancement if either role is empty or the roster has not been submitted.

## Parity Gate

### Candidate/static validation

- Testing: `admin/scoring-tests/index.php` and isolated `bdc_test_*` callback/Final transfer path updated first.
- Live: `admin/scoring/core.php` mirrors the same validation, prompt, callback generation and Final creation.
- Projector: no data contract changed; the resulting Final uses the existing shared Final projection workflow.
- Added `tests/direct-final-prompt-v364.php` plus retained `tests/role-advancement-v363.php` boundary coverage.
- `git diff --check` required before publishing. PHP executable validation remains unavailable in this Workspace runtime.

### Staging/runtime validation

- Pending deployment of the exact `develop` commit to Staging.
- Validate 5/5 direct Final, 8/5 role-specific Heats, 8/6 normal Heats, roster locking, Final pairing and projector Final selection.
- Production promotion remains blocked until Staging passes.

## Migration

- No database migration.
- No configuration change.
