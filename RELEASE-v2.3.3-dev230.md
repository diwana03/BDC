# BDC 2.3.3-dev230

## Existing Automatic score repair

- Automatic Calculate & Sort and Submit Scores now use the shared transactional calculation service.
- Existing calculated result rows for the round are replaced safely before the new result set is inserted.
- Existing judge marks and saved drafts are preserved and reused.
- Test and Live Automatic scoring use the same calculation engine and replacement behavior.

## Scope

- Automatic Heats and Semi-Final calculation only.
- Manual scoring and projector rendering are unchanged.
- No database migration.
- Push target: `develop` only; Production is not modified.
