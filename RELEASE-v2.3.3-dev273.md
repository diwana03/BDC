# BDC 2.3.3-dev273 — build 2979

## Emergency Final score recovery

- Adds **Unlock All Locked Scores** at the bottom of Automatic Final scoring.
- Reopens every currently submitted judge session together while preserving all entered placements.
- Restricts the emergency control to Scorer, Master Scorer and Super Admin roles.
- Requires both an emergency reason and typed `UNLOCK ALL` confirmation.
- Records the operator, time, round, reason, affected count and judge IDs in the scoring audit.
- Keeps each reopened column editable until its judge submits again; resubmission independently restores that judge's lock.

## Projector reveal safety

- Routes Live and Test projector links through a safe launch screen.
- Every newly opened projector link clears active effects and loops and starts on the neutral Holding Screen.
- Preserves the controller-only choice between muted and sound-enabled projection.
- Prevents callbacks, pairings, competitors or results from being exposed merely by opening the projector URL.

## Parity Gate

- **Testing Score Dashboard:** bulk submitted-session discovery, emergency reason and confirmation validation, placement preservation, audit record, lock count and bottom control checked against isolated `bdc_test_*` data paths.
- **Live Scoring Dashboard:** matching authorization, validation, canonical-session bulk reopen, placement preservation, audit record, lock count and bottom control checked against Live tables.
- **Live Scoreboard / projector:** shared Test/Live launch URL, Holding Screen reset, effect clearing, loop clearing, muted launch and sound-enabled launch checked statically.
- Candidate/static validation: `git diff --check`, JSON parsing, Test/Live action-label parity, service-query parity and projector launch routing passed.
- PHP runtime, database-backed unlock behavior and browser projector launch remain pending Staging validation.
- Production promotion remains blocked until Staging confirms bulk reopening, independent resubmission locks and Holding Screen startup.

## Deployment

- Database migration: none.
- Staging deployment: pending.
- Production deployment: not performed.
