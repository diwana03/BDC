# Release 2.3.3-dev609

## Emergency projector recovery

- Disables the dev608 row-aware stylesheet and runtime row calculation.
- Restores the known-working dev607 competitor and judge rendering behavior.
- Does not change scoring data, judge links, results, projector controls or the database.

## Validation status

- Shared projector regression checks passed.
- PHP runtime lint is unavailable in this workspace.
- Staging/runtime verification is required immediately after deployment.
- Production promotion remains blocked until the projector page is confirmed alive on Staging.

## Migration and deployment

- Database migration: none.
- GitHub `develop`: emergency candidate prepared.
- Staging: not deployed.
- Production: not deployed.
