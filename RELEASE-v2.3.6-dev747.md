# BDC 2.3.6-dev747 — Legacy Published Archive Recovery

## Production failure corrected

The detailed archive refresh reached the server but stopped with `The published Heats repository file was not found`. The Salsa Heats page was still publicly available, but its result-document record retained a legacy storage path created before the protected result repository format.

## Safe correction

- Loads the existing published result URL together with its stored path.
- Tries the canonical `protected-results://` path first.
- If that path is legacy or stale, resolves the exact `file` value already used by `result-file.php`.
- Supports older direct file paths by filename only.
- Confines every fallback to the current environment's protected result repository.
- Replaces the existing Heats, Final and Points files in place, preserving their public URLs.
- Retains the protected backup, checksum audit and restoration workflow from dev745.

## Data boundary

This changes archive file lookup only. It does not recalculate or modify scores, placements, finalists, manual promotions, points, publication records or projector state.

## Parity Gate

- **Testing Score Dashboard:** `admin/scoring-tests/publish.php` uses the same legacy archive recovery and protected replacement workflow.
- **Live Scoring Dashboard:** `admin/scoring/publish.php` uses identical recovery logic for the Production failure shown on Salsa Open Final round 68.
- **Live Scoreboard / projector:** unchanged because archive refresh only replaces repository HTML snapshots.

## Validation

- Focused standard archive refresh checks pass.
- Legacy Salsa filename, canonical path, direct legacy path, missing file and path traversal cases are covered by `tests/standard-result-archive-legacy-path-v747.js`.
- Full JavaScript regression gate: 277 passed, 0 failed.
- Two unchanged wrappers were not runnable because PHP CLI is unavailable in this workspace.
- PHP and browser runtime verification remain required on Staging before Production promotion.

## Deployment status

- Candidate only until committed and pushed to `develop` for Staging/Test.
- No database migration.
- Production remains untouched.
