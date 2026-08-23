# BDC 2.3.3-dev363

## Role-specific Heats advancement

- Keeps the tier based on the larger Leader or Follower roster: Tier 1 = 5 YES, Tier 2 = 10 YES, Tier 3 = 15 YES.
- Advances a role intact when its roster is at or below that tier's YES quota; that role does not require Heats marks.
- Continues Heats normally for the other role when it exceeds the quota.
- Uses only the available alternate positions above the quota, capped at A3.
- Applies the same rule to Test and Live automatic judge screens, progress tracking, manual calculation validation and printable backup judge sheets.
- Preserves the established score weights, Chief Judge score handling, ranking, tie rules, callback transfer and projection result data.

## Verification

- Added a focused boundary regression for all three quotas plus the 8 Leader / 7 Follower, 8 Leader / 6 Follower and 8 Leader / 5 Follower mixed-role cases.
- Confirmed the mathematical ranking and weighting paths were not rewritten.

## Parity Gate

### Candidate/static validation

- Testing Score Dashboard: `admin/scoring-tests/index.php`, `admin/scoring-tests/print.php`, `test-judge-scoring/index.php`, `app/Services/TestAutomaticJudgeService.php` and isolated `bdc_test_*` flow checked.
- Live Scoring Dashboard: `admin/scoring/core.php`, `admin/scoring/print.php`, `judge-scoring/index.php` and `app/Services/AutomaticJudgeBrowserService.php` checked against the same shared advancement rule.
- Projector: no rendering or projector controller change was required; existing callback and Final data contracts remain unchanged through `app/Services/HeatsScoringEngine.php`.
- Automated candidate gate: pending because the current execution environment does not provide a PHP runtime. The candidate must not be pushed until PHP syntax and regression checks pass.

### Staging/runtime validation

- Pending deployment of the exact validated `develop` commit to Staging.
- Production promotion remains blocked until the Test, Live and projector browser workflows pass on Staging.
