# BDC v2.3.3-dev338

## Compact Heats judge scoring

- Removes the `LATER` choice from Heats and Semifinal judge scoring.
- Keeps the established YES, A1, A2, A3 and NO quota/tiering logic unchanged.
- Adds a compact optional comment field to every Heats/Semifinal competitor card in Testing and Live judge screens.
- Comments are private working notes stored on the judge's current browser/device and do not affect scores, completion or submission.
- Treats legacy `LATER` marks as NO/unselected on judge and live-monitor displays.

## Multiple automated backup schedules

- Replaces the single effective automation slot with a schedule list supporting Daily, Weekly and Monthly jobs for Full Portal, Database Only and Website Files backups.
- Migrates the currently enabled singleton schedule into the new list so the existing job is visible instead of lost.
- Shows each schedule's Active/Disabled state, Last Run and Next Run, with Edit, Enable/Disable, Run Now and Delete controls.
- Blocks exact duplicate schedule slots and tells the administrator to edit the existing row.
- Keeps one cron URL; each cron request checks every active schedule and runs all jobs that are due.
- Retention and Google Drive connection remain shared across all schedules.

## Parity Gate

- Testing judge screen: `test-judge-scoring/index.php` and quota controls checked.
- Live judge screen: `judge-scoring/index.php` and AJAX quota validation checked.
- Testing/Live monitoring and projector data: `admin/scoring-tests/automatic-live-data.php` and `admin/scoring/automatic-live-data.php` checked so legacy `LATER` is never projected.
- Candidate/static validation: source marker test, theme contrast marker, JSON parsing and `git diff --check` passed.
- Backup validation: multiple-schedule schema, legacy migration, duplicate prevention, CRUD controls and all-due cron routing checked.
- Staging/runtime validation: pending user deployment of this exact `develop` candidate.
- Production promotion remains blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: automatic creation of `bdc_backup_schedules`; the enabled legacy schedule is copied once when the schedule list is initially empty.
- Deployment target: GitHub `develop`; user deploys to Staging.
- Production: unchanged.
