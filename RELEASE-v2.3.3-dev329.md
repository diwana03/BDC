# BDC v2.3.3-dev329

## Backup deletion, recovery access and Google Drive controls

- Adds a Delete Backup action to shared Test and Live scoring checkpoints and the central Scoring Backups & Transactions page.
- Requires backup-management permission, CSRF validation, a reason, typed `DELETE BACKUP` confirmation and writes an audit transaction containing the deleted checkpoint identity and checksum.
- Deletes only the selected checkpoint; current scores, results and the scoring round are not changed.
- Keeps a permanent Backups & Recovery link visible on both Test and Live scoring dashboards and links each shared checkpoint panel to the central recovery URL.
- Adds portal-backup Apply and Delete controls for Super Admin with typed confirmation.
- Applying a Database or Full Portal backup restores its database payload only after creating a fresh database safety backup; website files and `config/config.php` are not overwritten by the web recovery action.
- Separates server retention from Google Drive retention and accepts either a Drive folder ID or full folder URL.
- Makes the Google Drive setup sequence explicit while retaining the existing private service-account JSON storage, connection test, scheduled upload and checksum history.

## Validation

- Candidate/static: shared Test/Live handlers, exact backup ID and data-mode scoping, permission, CSRF, typed confirmation, reason and audit markers checked.
- Candidate/static: portal apply/delete controls, automatic pre-restore database safety backup, separate retention fields, Drive folder normalization, version JSON and whitespace checks passed.
- PHP runtime: unavailable in the development container; syntax and database execution remain pending on Staging.
- Database migration: additive `server_keep_count` and `drive_keep_count` columns, provisioned idempotently by `SchemaUpdater`.
- Google Drive connection: code and configuration screen ready; actual folder connection requires the user’s Drive folder URL and service-account JSON on Staging.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard: isolated backup deletion handler and permanent Test recovery URL checked.
- Live dashboard: matching backup deletion handler and permanent Live recovery URL checked.
- Shared backup panel and central recovery page: create, restore, delete, data-mode isolation and transaction history checked.
- Projector: no display, score, judge, callback, ranking or publication code changed.
- Staging/runtime: pending user deployment and browser validation; Production promotion remains blocked until that passes.
