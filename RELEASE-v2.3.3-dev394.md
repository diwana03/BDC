# BDC v2.3.3-dev394 — Recoverable System Test Runs

## Fixes

- Converts both controlled test actions to one-use, server-recorded runs followed by HTTP 303 Post/Redirect/Get, preventing refresh, back/forward, or browser resubmission from creating duplicate test events.
- Adds the missing nonce and double-click lock to the isolated Heats smoke test as well as the Live/Test parity test.
- Rejects an expired dashboard-approved link with HTTP 410 and a clear fresh-approval path before any test mutation can start.
- Blocks a scoped run when less than two minutes of approved access remains, so a long parity calculation cannot begin on a stale session.
- Persists every isolated and parity run, its PASS/FAIL/error state, paired Live/Test event and round IDs, evidence JSON, start time, and completion time.
- Adds Recent Controlled Runs with reopenable reports, making parity results recoverable and auditable after navigation or refresh.
- Scopes saved-run history and report reopening to the active dashboard approval, while full administrators retain complete history.
- Makes the Dance Cup projection regression check self-contained while preserving its environment-variable override mode.

## Database

- Additive migration: `database/migrations/20260824_0200_system_test_runs.php`.
- Creates `bdc_system_test_runs` only; no existing scoring table or configuration is replaced.
- No change to `config/config.php`.

## Candidate validation

- PASS: focused v394 regression checks for POST replay protection, stale-access preflight, durable run history and migration integration.
- PASS: existing v393 Live/Test parity safety and comparison checks.
- PASS: full JavaScript regression suite.
- PASS: repository diff and JSON validation.
- NOT RUNTIME-TESTED: Staging browser execution of refresh/back/forward/double-click, expiry boundary, report reopen, paired archive, and database migration. Production promotion remains blocked until the exact candidate is deployed and verified on Staging.

## Parity Gate

- Testing dashboard: `admin/scoring-tests/system-test.php` isolated `bdc_test_*` smoke run checked with nonce, PRG, durable report and Test event/round identifiers.
- Live dashboard: the same runner's unpublished Live parity path checked with pre-mutation access validation, durable paired identifiers and exact result report.
- Projector: no audience projector code or command changed in this release; existing Test/Live projector regression checks remain unchanged.

## Deployment

- Candidate target: public GitHub `develop`.
- User deploys the exact candidate to Staging through Release Manager.
- Production: unchanged and not approved by this candidate.
