# BDC v2.3.3-dev53

## Automatic Scoring Setup UI Fix

- Fixes the Automatic Scoring setup panel not appearing on the Heats screen.
- The integration now mounts against the actual legacy Judge Panel section rendered by the scoring core instead of a Registration Desk marker that is not present on this page.
- The new Automatic Scoring Setup is visible before judge scoring and includes the `+ Add Judge` control from dev50.
- Legacy Automatic Judge Panel and Judge Scores admin-entry sections are hidden once the new setup workflow is mounted.
- Judge setup still autosaves, supports explicit Save Draft, starts with three rows and allows unlimited additional judges.
- Judge links remain blocked until Judges & Competitors are confirmed.
- Release Manager workflow is unchanged.
