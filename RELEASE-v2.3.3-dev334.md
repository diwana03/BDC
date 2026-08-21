# BDC v2.3.3-dev334

## ModSecurity-safe Google Drive connection

- Delivers Google's OAuth authorization response in the browser fragment so the hosting firewall never receives the one-time authorization code in the callback URL.
- Relays the response to the authenticated BDC callback as an encoded same-origin JSON request.
- Preserves the existing session-bound OAuth state validation, Super Admin authorization, narrow `drive.file` scope and secure refresh-token storage.
- Retains backward-compatible query callback handling for authorization attempts started before this release.

## Validation

- Candidate/static: callback routes, fragment relay markers, OAuth state validation, version JSON and whitespace checks passed.
- Security: the client secret and refresh token remain outside Git; no portal-wide ModSecurity exception is required.
- Runtime: pending deployment to Staging and one Google consent/test cycle.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test and Live backup consumers continue using the same shared backup automation service.
- Backup Dashboard, manual runs and cron uploads remain unchanged after connection.
- Projector: not affected.
- Staging/runtime: deploy this release, reconnect Google Drive, run Test Google Drive and verify one Database Only backup before Production promotion.
