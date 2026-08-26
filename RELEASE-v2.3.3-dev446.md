# BDC 2.3.3-dev446 · build 3152

## Dance Cup Participants 500 repair

- Corrects the pending-approval lookup to use `bdc_dance_cup_events`, keeping Dance Cup data isolated from Jack & Jill events.
- Wraps participant/history dashboard reads in a safe failure boundary so a partial approval/history schema cannot produce an HTTP 500.
- Preserves all participant and scoring data; no database migration or destructive operation is included.

## Validation

- Added regression coverage for the isolated Dance Cup event join and safe dashboard loading.
- Existing JavaScript regression suite passed.
- Deploy to staging first; Production remains unchanged until explicitly deployed.
