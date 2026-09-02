# v2.3.3-dev573

## Emergency WDC runtime rollback

- Restores the exact Dance Cup Competitor implementation confirmed working in Production as dev571.
- Removes the dev572 comprehensive runtime expansion that returned HTTP 500.
- Retains WDC identities, active registrations, case-insensitive search, entry/completeness filters, Edit WDC and Adjust Photo.

## Safety

- No migration and no database writes.
- No BDC, SDC, scoring, result or championship-point changes.
- The comprehensive dashboard rebuild is blocked until the exact candidate passes Staging runtime testing.

## Validation

- Full JavaScript regression suite: required before publishing.
- Production runtime verification: required after deployment.
