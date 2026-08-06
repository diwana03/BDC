# BDC 2.3.3-dev32

## Release Manager backup retention

- Keeps only the newest three full Release Manager deployment backups.
- Applies retention immediately after each new Production safety backup succeeds.
- Adds a Staging-only Super Admin backup inventory with backup date and size.
- Adds CSRF-protected manual deletion with path validation, confirmation and an active-deployment safety lock.
- Removes rollback availability automatically when its underlying backup is deleted.

No database migration is required. Production is not deployed by this change.
