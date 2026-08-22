# BDC 2.3.3-dev360 — Manual J&J Judge Search

## Outcome

- Replaces the browser-native judge suggestion list with a visible Judge Database result menu from the first typed character.
- Uses the selected Judge Database identity in Live manual scoring while preserving new-name entry.
- Applies the same search behaviour to isolated Test and Live manual J&J setup, including dynamically added judge rows.
- Removes the overlapping floating `Backups & Recovery` shortcut from both manual dashboards; the complete inline recovery panel remains available.

## Parity Gate

### Candidate/static validation

- Testing dashboard checked: `admin/scoring-tests/index.php`.
- Live dashboard checked: `admin/scoring/core.php`.
- Shared directory endpoint and UI checked: `admin/scoring/judge-directory-search.php` and `public/js/scoring-judge-directory.js`.
- Projector checked as unaffected: this change does not alter judge assignments, scoring calculations, results or projection rendering.
- PHP parsing, JavaScript syntax and release regression checks passed locally.

### Staging/runtime validation

- Pending deployment of this exact `develop` commit by the user.
- Confirm one-character searches, selection, new-name entry and added judge rows in Test and Live on Staging.
- Production promotion remains blocked until the runtime checks pass.

## Migration and Deployment

- Database migration: none.
- Candidate target: GitHub `develop`.
- No Production action performed.
