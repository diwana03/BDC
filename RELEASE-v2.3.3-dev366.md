# BDC 2.3.3-dev366

## Manual Live Preview tie parity

- Stops the manual Test and Live previews from breaking equal totals using Chief score, YES count or entry ID.
- Groups equal totals before assigning preview statuses.
- Shows every dancer in a callback-boundary, alternate-boundary or tied-alternate group as **Tie Pending** with the same shared rank.
- Keeps ties entirely inside the callback zone as callbacks, matching the established server engine.
- Updates callback, alternate and tie row colours immediately as marks change.
- Does not change saved scores, official calculation, Chief Judge decisions, weights or Final logic.

## Screenshot regression

- For totals `30, 30, 30, 20, 20, 20` with five callbacks, all three dancers on 20 display **Tie Pending · Live #4**.
- For totals `30, 30, 30, 30, 20, 10`, the dancer on 20 remains Callback #5 and the dancer on 10 remains Alternate #6.

## Parity Gate

### Candidate/static validation

- Testing: `admin/scoring-tests/index.php` updated first.
- Live: `admin/scoring/core.php` contains the identical grouped preview logic.
- Server scoring and projector data were not changed.
- Added executable Node regression `tests/manual-live-tie-preview-v366.js`.
- Corrected the Tier 2 and Tier 3 mixed-role examples in the prior regression/release note to use role counts that actually activate those tiers.

### Staging/runtime validation

- Reproduce the screenshot scores and confirm all three tied Leaders show Tie Pending before and after Calculate & Sort.
- Confirm the untied Follower callback and alternate remain unchanged.
- Resolve the Leader boundary tie through the existing Chief Judge decision and verify Final transfer/projector data.
- Production promotion remains blocked until Staging passes.

## Migration

- No database or configuration migration.
