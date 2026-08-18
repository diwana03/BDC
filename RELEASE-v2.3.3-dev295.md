# BDC 2.3.3-dev295 · Build 3001

## Archive checkpoint consolidation

- Creates one protected Final Archive Snapshot for every scoring round before a completed event is archived.
- Preserves explicitly created manual checkpoints and the latest pre-restore emergency checkpoint.
- Removes redundant automatic judge-submission and dashboard-action checkpoints after the final snapshot succeeds.
- Runs snapshot creation, checkpoint cleanup and event archiving in one database transaction.
- Keeps archive restoration intact while preventing checkpoint storage from growing indefinitely.
- Marks protected checkpoints clearly in the backup history.

## Projection launch isolation

- Opens Live Screen / Projection Control links in a new tab across Manual, Automatic and shared round navigation.
- Applies the same behavior to the isolated Test dashboard.
- Keeps BDC Home, Dashboard and ordinary scoring navigation in the current tab.
- Keeps projector feed and Emcee control launch actions in separate protected tabs.

## Migration

- `20260819_0110_scoring_backup_protection.php` adds the idempotent `is_protected` checkpoint flag.
- Existing manual checkpoints are automatically marked protected.

## Parity Gate

### Candidate/static validation

- Testing Score Dashboard: shared backup service verified against isolated `bdc_test_*` tables.
- Live Scoring Dashboard: archive action creates and consolidates Live checkpoints transactionally.
- Live Scoreboard/projector: checked and unaffected; archive maintenance does not change projected data or presentation code.
- Projection parity: Test and Live projection launch links use `target="_blank"` with `rel="noopener"`.
- Static regression: `tests/archive-backup-consolidation-v295.php`.
- PHP runtime syntax validation is unavailable in the local workspace and must run through the Staging deployment health check.

### Staging/runtime validation

- Pending deployment of this exact `develop` candidate through Release Manager.
- Production promotion remains blocked until Staging migration, archive, restore and health checks pass.

## Deployment

- Candidate branch: `develop`
- Production: not deployed and not modified
