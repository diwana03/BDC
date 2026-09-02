# v2.3.3-dev569

## WDC editor 500 hotfix

- Fixes the WDC editor history query that requested nonexistent `event_name` and `category_name` columns from `bdc_dance_cup_result_history`.
- Joins the event name from `bdc_events` and category name from `bdc_dance_cup_competitions` using the ledger's permanent foreign keys.
- Adds a regression check tied to the real schema ownership.

## Validation and deployment gate

- Full JavaScript regression suite: PASS.
- Static diff validation: PASS.
- Database migration: none.
- Scoring, Test, Live and projector files: unchanged.
- Staging WDC list, Edit WDC and photo upload: NOT RUNTIME-TESTED until this exact candidate is deployed.
