# BDC v2.3.3-dev331

## Backup Dashboard retention-schema repair

- Fixes `Unknown column 'server_keep_count' in 'field list'` when saving Backup Dashboard settings after deploying dev329/dev330.
- Makes `BackupAutomationService` verify and add the two retention columns before the first settings read, save, scheduled run or Google Drive connection test.
- Keeps the existing idempotent `SchemaUpdater` migration as the full-install path while making the Backup Dashboard safe when database migrations were not run separately.
- Preserves the selected Google Drive folder, uploaded credential path, backup history and existing backup files.

## Validation

- Candidate/static: constructor order, settings-table inspection, additive column definitions, version JSON and whitespace checks passed.
- Database migration: additive and idempotent; no existing rows or backups are deleted.
- PHP/database runtime: pending deployment to Staging because PHP is unavailable in the development container.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test and Live scoring backup controls: unchanged from dev330.
- Shared scoring backup panel and central recovery page: unchanged from dev330.
- Portal Backup Dashboard and cron runner: both instantiate the repaired shared automation service.
- Projector: not affected.
- Staging/runtime: deploy this exact commit and retry Save Backup Settings before Production promotion.
