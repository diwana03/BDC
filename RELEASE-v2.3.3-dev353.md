# BDC 2.3.3-dev353

## Dance Cup saved-judge visibility

- Adds an independent premium Assigned Judges roster immediately below Dance Cup setup.
- Shows saved judges before any competitor exists, including J-number, Chief Judge identity and panel count.
- Makes the empty scoring-sheet message identify whether competitors or judges are actually missing.
- Makes Judge Display Order read the assigned roster, so ordering works before the scoring table exists.
- Uses the same category workspace for isolated Test and Live Dance Cup data.

## Validation

- Candidate/static: JSON, roster markup, judge-order fallback, shared category table selection and repository whitespace checked.
- Migration: none.
- Deployment: candidate prepared for GitHub `develop`; Staging deployment remains with the user.

## Parity Gate

- Testing Score Dashboard: shared `admin/dance-cup/category.php` checked with `bdc_test_dance_cup_*` selection.
- Live Score Dashboard: the same shared category workspace checked with `bdc_dance_cup_*` selection.
- Projector: no projected data or scoring calculation changed in this release.
- Runtime/Staging: pending deployment and browser verification. Production promotion remains blocked until that pass is complete.
