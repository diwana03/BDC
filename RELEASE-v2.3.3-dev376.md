# BDC v2.3.3-dev376

## Correct judge-mark labels

- Shows `N/A` only when a judge is not assigned to the competitor's Leader or Follower role.
- Shows `—` when the judge is assigned to that role but has not marked that competitor.
- Shows the actual mark when submitted, including a real numeric zero.
- Applies the rule to Testing and Live score reports, Testing and Live Automatic scoring matrices, and the shared Test/Live projector score matrix.
- Keeps the dev375 Automatic scorer scroll-position restoration unchanged.

## Validation

- Static role-assignment regression covers readable, paginated and all-judge reports.
- Automatic Testing and Live matrices distinguish role assignment before rendering missing marks.
- Shared projector matrix already uses the exact same `N/A` versus `—` rule and is protected by the parity regression.
- No database migration is required.

## Parity Gate

- Testing: `admin/scoring-tests/result.php`, `admin/scoring-tests/automatic-inline.php`.
- Live: `admin/scoring/result.php`, `admin/scoring/automatic-round.php`.
- Projector: `live-display/feed.php` inspected and parity-tested; it already renders `N/A` only for an unassigned role and `—` for an assigned missing mark.
- Candidate/static validation: completed.
- Staging/runtime validation: pending deployment of the exact `develop` commit.
- Production promotion remains blocked until Staging runtime validation passes.
