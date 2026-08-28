# BDC 2.3.3-dev462

## Backup scheduler retention and Google Drive repair

- Full backups now keep only the final recovery ZIP after embedding the database and website archives.
- Failed full-package creation removes temporary component archives safely.
- Server retention cleans old files by backup type using the configured server limit.
- Google Drive retention counts uploaded Drive files only and is independent from the current upload toggle.
- Google OAuth and service-account uploads use streamed resumable transfers instead of loading the complete archive into PHP memory.
- Concurrent cron requests cannot create overlapping duplicate backups.
- Google Drive failures retain the successful local backup and show the provider error in backup history.

No scoring, competition, competitor, judge, point, result or projection behavior is changed.
