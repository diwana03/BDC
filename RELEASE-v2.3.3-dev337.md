# BDC v2.3.3-dev337

## Verified Google Drive storage

- Validates the configured `BDC_Backup` folder before every OAuth upload and automatically creates a replacement when the saved folder is missing or trashed.
- Verifies every uploaded Drive file after completion and confirms that its real parent is the active backup folder.
- Adds **Repair & Verify Drive Storage** to recreate a broken folder, reattach accessible history files and mark genuinely missing files as failed.
- Adds **Open Backup Folder** using the currently verified folder ID.

## Validation

- Static source markers cover folder validation, upload readback, parent repair, history reconciliation and the two new dashboard actions.
- `git diff --check` and JSON parsing passed.
- PHP CLI is unavailable in this workspace; runtime validation remains required on Staging.
- Scoring Test, Live and projector surfaces are unchanged.

## Migration and deployment

- Database migration: none.
- Deployment: GitHub `develop`; user deploys to Staging.
- After deployment, click **Repair & Verify Drive Storage** once, then open the verified folder.
- Production: unchanged.
