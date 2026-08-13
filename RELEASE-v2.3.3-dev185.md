# BDC 2.3.3-dev185

## Summary

- Adds an independent date and time to every scoring round.
- Allows Heats, Semifinal, and Final to occur on different days or at different times within one event.
- Adds a Round Date & Time field when creating the first round and when moving callbacks to Semifinal or Final.
- Shows the round schedule at the top of Manual and Automatic scoring forms, in Saved Rounds, and on Test/Live projector screens.
- Existing rounds remain compatible and fall back to the overall event date until a round schedule is entered through Edit Draft Details.

## Validation

- Confirmed Testing and Live round creation accept the same `datetime-local` value and server-side format validation.
- Confirmed callback promotion carries an independently selected next-round schedule.
- Confirmed direct-to-Final and special-category creation also save the round schedule.
- Confirmed existing unscheduled rounds use a safe event-date fallback.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is unavailable in this workspace; migration execution and end-to-end scheduling remain part of Staging validation.

## Database migrations

- Adds nullable `scheduled_at DATETIME` to `bdc_scoring_rounds` and `bdc_test_scoring_rounds`.
- Migration: `20260814_0100_round_scheduling.php`.
- Existing scoring data is unchanged.

## Deployment

- Source release only. The user deploys `develop` to Staging through Release Manager.
- Production deployment is not performed by the coding agent.
