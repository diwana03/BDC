# BDC 2.3.3-dev186

## Summary

- Fixes the Production deployment failure reporting that applied migration `20260803_2200` was modified.
- Applies the same permanent compatibility correction to legacy wrapper migration `20260806_1700`.
- Retains all per-round scheduling changes from dev185.

## Root cause and correction

- Both legacy migrations call the shared, idempotent `SchemaUpdater` and previously included that mutable service in their historical checksums.
- Legitimate later schema additions therefore changed the calculated checksum of already-applied migrations.
- These two known wrapper migrations now checksum only their immutable migration files. Other dependency-aware migrations retain normal dependency checksum validation.
- Known historical checksums remain explicitly allowlisted. Unknown stored checksums and modified wrapper files still fail closed.

## Validation

- Confirmed the immutable wrapper files are byte-identical and calculate the stable checksum `d948b9cc2c9ebde5f7cd36aa684627e7feb4b941d65f6663a22df2f620f77714`.
- Confirmed the dev185 dependency checksum is recognized for installations created before this correction.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is unavailable in this workspace; the migration runner and new round-scheduling migration require Staging validation through Release Manager.

## Database migrations

- No new migration is added in dev186.
- No Production migration-history rows are modified or removed.
- The pending dev185 migration `20260814_0100_round_scheduling.php` will run after compatibility validation succeeds.

## Deployment

- Source release only. Deploy dev186 to Staging first through Release Manager.
- Production deployment is not performed by the coding agent.
