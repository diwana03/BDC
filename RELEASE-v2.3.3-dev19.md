# BDC 2.3.3-dev19

## Backup Dashboard enhancement

- Adds separate manual controls for Full Portal, Database Only and Website Files backups.
- Keeps manual backup actions independent from the configured automated backup type.
- Adds an Available Recovery Backups table covering manual and automated local archives.
- Shows backup type, creation time, size and checksum with a secure download action.
- Renames the scheduled action so it is not confused with the three manual backup options.
- Retains the point-adjustment workflow, dashboard approval alerts, duplicate finder, overlap-safe participant merging and Production Results Repository sync from previous releases.

This release changes only the Backup Dashboard interface and release metadata. It does not change scoring, points, migrations, database schema or Production data.
