# BDC v2.3.3-dev335

## Callback-free Google Drive authorization

- Replaces redirect-based Google authorization with the official Google Identity Services popup code flow.
- Returns the one-time code to the already authenticated Backup Dashboard and exchanges it through a same-origin encoded request.
- Avoids navigating through the callback URL that the hosting provider's ModSecurity rules reject.
- Preserves Super Admin access, session-bound state validation, the narrow `drive.file` scope and private refresh-token storage.

## Validation

- Candidate/static: Google popup configuration, callback relay, server-side origin exchange, OAuth state validation, version JSON and whitespace checks passed.
- Security: client secret and refresh token remain server-side and outside Git.
- Runtime: pending deployment to Staging and one popup authorization/test cycle.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test and Live backups continue through the same shared service.
- Backup Dashboard, manual runs, retention and cron uploads remain shared and unchanged after authorization.
- Projector: not affected.
- Staging/runtime: deploy, click Connect Google Drive, approve the popup, run Test Google Drive and verify one Database Only backup.
