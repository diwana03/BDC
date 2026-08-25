# BDC v2.3.3-dev395

Release date: 25 August 2026  
Build: 3101  
Branch: `develop`

## Dance Cup complete automatic workflow

- Completes Automatic Dance Cup scoring from judge setup and secure links through live progress, calculate/review, and final Submit & Lock.
- Requires every active competitor × judge × criterion mark, current calculated results, and every automatic judge submission before the round can lock.
- Uses the shared Test/Live scoring service and preserves strict `bdc_test_dance_cup` versus `bdc_dance_cup` isolation.
- Keeps Manual Scoring available as the fallback without changing Jack & Jill behavior.

## No-refresh Manual and judge scoring

- Intercepts Dance Cup Manual Save Draft, Calculate, Checkpoint, and Submit actions so the scorer remains at the current page position.
- Adds debounced judge-sheet draft autosave and AJAX submission without page reload.
- Treats an emptied score as a real cleared mark instead of silently keeping the old value or converting it to zero.
- Invalidates calculated results whenever any mark changes, preventing a stale ranking from being submitted or projected.

## Live projection parity

- Uses the safe Holding Screen projector launcher from Automatic Dance Cup.
- Opens Projection Control in a separate tab from both Manual and Automatic scoring.
- Repaints the existing projector from current entries, judges, submissions, and results on the established five-second hidden-tab-safe poll.
- Applies competition ranking to provisional ties (for example 1, 1, 3) exactly like calculated results.

## Mandatory sanity gate

Automated test: `node tests/dance-cup-workflow-v395.js`

The gate checks:

- Test/Live table isolation.
- Manual and judge no-refresh wiring.
- Cleared-mark deletion and stale-result invalidation.
- Automatic judge-submission and final-lock gates.
- Safe separate-tab projection launch.
- Projector repaint data, polling/backoff behavior, and provisional tie parity.
- A model workflow proving incomplete automatic judging cannot lock.

## Database and deployment

- Schema migration: none. Existing Dance Cup automation tables remain self-provisioning.
- Jack & Jill scoring and projection are unchanged.
- Staging runtime validation: **not tested from Codex**.
- Production: **blocked until the exact develop commit passes Staging runtime validation**.
