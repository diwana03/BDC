# BDC v2.3.3-dev391

## Production-hosted isolated system testing

- Adds a Super Admin-only one-click system test runner under the Scoring Tests Dashboard.
- Creates only hidden draft `bdc_test_*` events, rounds, entries, judges, sessions, marks and results.
- Uses the shared scoring calculation service and the existing automatic judge submission service.
- Verifies roster creation, judge assignment, submission locks, mark persistence, result persistence, callback counts and unresolved ties.
- Provides direct links to the generated Automatic Test round and Test projector control.
- Archives rounds and marks runner-owned events as cancelled only when their names carry the protected `BDC SYSTEM TEST - DO NOT PUBLISH` prefix.
- Sends no notifications and never writes to Live scoring, public results or point tables.

## Validation

- Focused runner isolation regression: PASS through the available Node runtime.
- Diff whitespace validation: PASS.
- PHP syntax validation: NOT RUNTIME-TESTED because PHP is unavailable in this workspace.
- Complete JavaScript regression suite: PASS, including Dance Cup projection and shared Test/Live Final synchronization.
- Repairs stale regression assertions that were pinned to superseded cache versions or an outdated Final Judge heading while preserving their feature-level gates.
- Staging runtime: NOT RUNTIME-TESTED; Production promotion remains blocked.

## Parity Gate

- Testing Score Dashboard: runner entry point and isolated shared-engine scenario included.
- Live Scoring Dashboard: unchanged; runner statically forbids Live event and round inserts.
- Projector: generated Test round links to the shared Test projector control; audience runtime not yet tested on Staging.

## Migration and deployment

- Database migration: none.
- Configuration changes: none; `config/config.php` unchanged.
- Deployment: candidate for `develop` only after all local gates pass.
