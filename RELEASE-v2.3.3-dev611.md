# Release 2.3.3-dev611

## Empty projector 500 recovery

- Removes the full-document output-buffer rewrite from `live-display/feed.php`.
- Returns holding, competitor and judge HTML directly.
- Prevents shutdown-time rendering failures from producing an empty HTTP 500 response.
- Leaves projector tokens, state, database records and scoring calculations unchanged.

## Validation status

- Direct-render regression check passed.
- Projector token hot-path and existing roster checks passed.
- PHP runtime lint is unavailable in this workspace.
- Deploy to Staging and verify the supplied projector link before Production.

## Migration and deployment

- Database migration: none.
- Staging: not deployed.
- Production: not deployed.
