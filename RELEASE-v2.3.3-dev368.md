# BDC 2.3.3-dev368

## Manual Final Judge Setup order

- Restores the missing **Final Judges** panel on the Manual Final dashboard before matching begins.
- Places the manual workflow in the operational order: Final Judges → Emcee Matching Link → Match Competitors → Confirm Final Pairing → Relative Placement scoring.
- Allows the scorer to search or enter Final judges, add or remove judges and select one Chief Judge before the Emcee starts matching.
- Keeps actual Relative Placement entry locked until the fixed couples are confirmed.
- Prevents a duplicate Final Judges panel after pairing confirmation.
- Does not change Automatic Final setup, judge links, sessions, scoring, locking or submission behavior.

## Parity Gate

### Candidate/static validation

- Testing implemented first in `admin/scoring-tests/index.php` with isolated Test judges.
- Live mirrored in `admin/scoring/core.php` with Judge Database search and profile linkage preserved.
- Emcee access and projector behavior from dev367 are unchanged.
- Automatic Final rendering remains on its existing post-pairing path.
- Added executable regression `tests/manual-final-judges-before-pairing-v368.js`.

### Staging/runtime validation

- Open an unpaired Manual Test Final and confirm Test Final Judges appears above Test Emcee Matching Link.
- Save at least three unique judges with exactly one Chief, refresh, and confirm the panel persists before pairing.
- Repeat on a Live Manual Final using Judge Database search.
- Confirm couples and verify the same judges appear in manual Relative Placement without a duplicate setup panel.
- Open an Automatic Final and verify its existing judge workflow is unchanged.
- Production promotion remains blocked until Staging passes.

## Migration

- No database or configuration migration.

## Deployment

- Candidate is for GitHub `develop` and exact-commit Staging deployment only.
- Production remains untouched.
