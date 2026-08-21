# BDC v2.3.3-dev332

## Google Drive shared-folder access repair

- Fixes the misleading Google Drive `404 File not found` returned after a service account was correctly granted Editor access to an existing backup folder.
- Replaces the picker-oriented `drive.file` OAuth scope with the Drive scope required by this server-to-server backup workflow.
- The service account remains isolated: it can access only Drive items explicitly shared with its service-account email.
- Preserves the stored private key, folder ID, backup settings, retention rules and existing recovery archives.

## Validation

- Candidate/static: OAuth claim, folder normalization, connection probe, upload parent and version JSON checks passed.
- Google documentation: `drive.file` is per-file access for picker/app-selected files; BDC is a service-account server backup without a picker.
- Runtime: pending deployment to Staging and a successful **Test Google Drive** response.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test and Live scoring backup controls use the same central backup service and require no separate scoring changes.
- Shared Backup Dashboard and cron uploads both use the repaired Google Drive service.
- Projector: not affected.
- Staging/runtime: deploy this exact release and run **Test Google Drive** before enabling automation or Production promotion.
