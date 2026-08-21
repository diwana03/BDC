# BDC v2.3.3-dev336

## Google Drive backup link correction

- Fixed the **Open** action for successfully uploaded Google Drive backups.
- Existing history rows now use their saved Google Drive file ID to build the canonical file URL, so no backup needs to be uploaded again.
- New uploads save the same canonical Drive file URL instead of relying on the provider-returned `webViewLink`.

## Validation

- Confirmed the change is limited to backup history link generation and does not alter backup creation, upload, retention, recovery, scoring, Testing, Live, or projector behaviour.
- Verified the canonical URL is URL-encoded and rendered through the existing HTML escaping helper.
- PHP CLI is unavailable in the local workspace; static source and diff validation completed.

## Migration and deployment

- Database migration: none.
- Deployment: publish to GitHub `develop`; the user deploys the candidate to Staging.
- Production: unchanged.
