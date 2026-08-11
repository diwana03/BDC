# BDC v2.3.3-dev50

## Automatic Scoring Setup Workflow

- Replaces the old Automatic judge-entry experience with a persistent setup draft.
- Judge setup autosaves while names, scopes and Chief Judge selection are edited.
- Keeps an explicit Save Draft action as a manual safety save.
- Starts with three judge rows and supports unlimited additional judges through `+ Add Judge`.
- Competitors and bibs continue to come from the existing Registration Desk and are shown beside the judge setup for review.
- Judge browser links are not generated until `Confirm Judges & Competitors` succeeds.
- Confirmation validates at least three judges for each required role, exactly one Chief Judge, active Leaders and Followers, and valid unique bibs.
- Once confirmed, the setup is locked and secure judge links are enabled.
- Automatic Judge Browser scoring remains live, autosaving and Submit-to-Lock as introduced in dev47.
- Existing calculation, Relative Placement, points, BDC ID and Release Manager workflows are unchanged.
