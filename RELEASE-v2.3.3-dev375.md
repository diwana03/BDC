# BDC v2.3.3-dev375

## Automatic scoring usability

- Preserves the scorer's exact page position after **Save Scores Draft**, **Calculate & Sort**, and **Submit Scores** on the isolated Testing Automatic dashboard and the Live Automatic dashboard.
- Keeps server-side scoring, calculation, submission, callback and tie logic unchanged.
- Prints `N/A` for every genuinely missing judge mark on Leader and Follower score reports; entered zero marks remain `0`.

## Validation

- Static regression verifies all three automatic workflow actions are covered on Testing and Live.
- Static regression verifies missing-mark `N/A` output and zero-score preservation on Testing and Live reports.
- `VERSION.json` parses successfully.
- No database migration is required.

## Parity Gate

- Testing Score Dashboard: `admin/scoring-tests/automatic-inline.php`, `admin/scoring-tests/result.php`.
- Live Score Dashboard: `admin/scoring/automatic-round.php`, `admin/scoring/result.php`.
- Projector: checked; no projector data, state, rendering, score calculation or navigation is changed.
- Candidate/static validation: completed.
- Staging/runtime validation: pending deployment of the exact `develop` commit.
- Production promotion remains blocked until Staging runtime validation passes.
