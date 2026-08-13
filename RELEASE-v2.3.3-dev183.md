# BDC 2.3.3-dev183

## Summary

- Adds a dedicated **Event Date** column to Saved Rounds.
- Keeps **Updated** as a separate system timestamp so users can distinguish the competition date from the last edit time.
- Displays dates as `13 Sep 2026` and shows `Date pending` when no event date is recorded.
- Applies the same layout to Testing and Live scoring dashboards.

## Validation

- Confirmed both Saved Rounds queries provide `event_date`.
- Confirmed Testing and Live render the same Event Date column and fallback label.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is unavailable in this workspace; visual verification remains part of Staging validation.

## Database migrations

- No migration added.
- No destructive database operation.

## Deployment

- Source release only. The user deploys `develop` to Staging through Release Manager.
- Production deployment is not performed by the coding agent.
