# BDC 2.3.3-dev291 · Build 2997

## Scoring Backup & Recovery

- Adds the same **Scoring Backup & Recovery** panel to Live and Test scoring dashboards.
- Creates an automatic snapshot before dashboard scoring changes and retains the latest 25 automatic snapshots for each round.
- Lets Scorers, Master Scorers and Super Admins create labelled manual backups.
- Shows a recovery preview with saved counts for marks, results, Final pairs and Final placements.
- Restores scoring state only after a required reason, exact `RESTORE SCORES` confirmation and final browser confirmation.
- Creates a pre-restore safety backup before any recovery begins.
- Restores judge scoring sessions together with marks so submitted and draft states remain consistent.
- Displays the latest 30 audited scoring transactions beside the backup history.

## Safety

- Recovery does not overwrite registrations, competitor records or judge configuration.
- Live and Test data remain strictly separated.
- Every completed restore records the operator, time, reason and snapshot hash in the scoring audit trail.
