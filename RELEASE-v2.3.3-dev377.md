# BDC v2.3.3-dev377

## Automatic Final judge setup parity

- Shows Automatic Final Judge Selection before Emcee Matching and Random Match on both Testing and Live.
- Allows the scorer to prepare the Final panel and Chief Judge before couples are matched.
- Keeps secure Automatic Final judge scoring links and Relative Placement entry locked until Final pairing is confirmed.
- Removes the duplicate post-pairing judge-selection form.
- Does not change Random Match, Relative Placement, judge-session locking, calculation, callback, result, or projector logic.

## Parity Gate

- Testing: `admin/scoring-tests/index.php` with isolated `bdc_test_*` data.
- Live: `admin/scoring/core.php` with Live scoring data.
- Projector: checked; no projector state or rendering changed.
- Candidate/static validation: completed.
- Staging/runtime validation: pending deployment of the exact `develop` commit.
- No database migration is required. Production remains blocked pending Staging validation.
