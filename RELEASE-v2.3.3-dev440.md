# BDC 2.3.3-dev440

## Emergency scoring hotfix

- Restores the proven Jack & Jill Manual and Automatic open-round header from dev438.
- Removes the only dev439 change inside the shared Jack & Jill scoring engine.
- Keeps Automatic event creation on the established all-round dashboard.
- Does not change Jack & Jill calculations, marks, callbacks, rankings, rosters or database records.
- Does not change Dance Cup calculations or the dev439 approval and participant features.

## Validation

- Complete JavaScript regression suite.
- `git diff --check`.
- PHP CLI is unavailable in this workspace, so authenticated PHP runtime remains **Not Runtime-Tested**.
- Deploy to Staging and open both Jack & Jill Automatic and Dance Cup Automatic before any Production promotion.
