# BDC 2.3.3-dev464

## Backup failure alerts

- Emails every active Super Admin when local backup creation fails.
- Emails every active Super Admin when a local backup succeeds but Google Drive upload fails.
- Shows persistent red alerts on the main BDC Admin Dashboard.
- Allows Super Admins to acknowledge dashboard alerts without hiding unresolved failures.
- Counts repeated failures and keeps the latest error and time visible.
- Limits repeat email alerts for the same unresolved failure to once every six hours.
- Automatically resolves the dashboard alert and emails a recovery notice after the next successful operation.
- Keeps alert delivery failures isolated so they can never interrupt backup execution.

No scoring, competition, competitor, judge, point, result or projection behavior is changed.
