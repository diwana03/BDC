# BDC 2.3.3-dev448 · build 3154

## Participant-first Dance Cup dashboard

- Removes optional result-history joins from the core participant row query.
- Always renders reusable participant profiles before attempting career-history enrichment.
- Loads career statistics and pending approvals in independent safe operations.
- A broken or partially migrated history/approval table can no longer hide valid participant profiles.
- No data is changed or deleted, and no database migration is included.

## Validation

- Added participant-first query isolation regression coverage.
- Complete JavaScript regression suite passed.
- Authenticated staging verification remains required after deployment.
