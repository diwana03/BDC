# BDC v2.3.3-dev333

## Google OAuth backup connection

- Replaces the unusable personal-Drive service-account upload path with owner-authorized Google OAuth.
- Adds secure OAuth Web client JSON upload, CSRF/state-protected Google authorization, offline refresh-token storage with `0600` permissions, automatic token renewal, connection testing and disconnect controls.
- Uses the narrow `drive.file` scope and automatically creates a BDC-managed `BDC_Backup` folder in the connected account, so uploads use the owner's storage quota without broad access to unrelated Drive files.
- Scheduled and manual automated runs share the same refreshed OAuth connection.
- Preserves all local backups, history, retention settings and legacy service-account support for Shared Drives.

## Validation

- Candidate/static: OAuth authorization, callback state validation, token exchange/refresh, managed-folder creation, upload parent, callback routes, dashboard controls, JSON version and whitespace checks passed.
- Secrets: OAuth client and refresh-token files remain outside Git and are stored under `storage/private` with `0600` permissions.
- Runtime: pending exact-release deployment to BDC_STAGING and completion of the Google consent callback.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test and Live scoring backup controls use the shared central backup service and remain unchanged.
- Backup Dashboard, forced runs and cron uploads all use the same OAuth-backed Drive client.
- Projector: not affected.
- Staging/runtime: deploy this release, authorize the Staging callback, test Drive, upload one backup and verify it appears in the generated BDC_Backup folder before Production promotion.
