# BDC 2.3.3-dev184

## Summary

- Displays the event date beside the event name at the top of open Heats, Semifinal, and Final scoring forms.
- Covers Manual and Automatic scoring workflows.
- Applies the same date format and `Date pending` fallback to Testing and Live.

## Validation

- Confirmed Live Manual round headers include `event_date` already loaded with the round.
- Confirmed Live Automatic round queries now load and display `event_date`.
- Confirmed Testing round headers display the same event-date information.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is unavailable in this workspace; visual verification remains part of Staging validation.

## Database migrations

- No migration added.
- No destructive database operation.

## Deployment

- Source release only. The user deploys `develop` to Staging through Release Manager.
- Production deployment is not performed by the coding agent.
