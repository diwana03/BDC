# BDC v2.3.3-dev393

## Production Live versus Test parity runner

- Adds a dashboard-approved parity scenario that creates one unpublished Live sandbox event and one isolated Test mirror.
- Uses the same 10 Leaders, 10 Followers, five judges and deterministic marks in both paths.
- Calculates Test and Live through `ScoringCalculationService` using their real table scopes.
- Compares every competitor total, Chief score, rank, callback status and alternate rank.
- Never publishes the Live sandbox, writes points or touches an existing event.
- Adds paired archive protection that accepts only the exact Live/Test system-test name prefixes.
- Adds a one-use form nonce and immediate button disabling to prevent delayed-click duplicate runs.

## Validation

- Complete JavaScript regression suite: PASS.
- Live/Test parity safety regression: PASS.
- Diff whitespace and JSON validation: PASS.
- PHP syntax: not locally runtime-tested because PHP is unavailable in this workspace.
- Production runtime: pending deployment and dashboard-approved parity execution.

## Parity Gate

- Testing Score Dashboard: identical mirrored entries, judges, marks and shared calculation path.
- Live Scoring Dashboard: unpublished sandbox event uses real `bdc_*` scoring tables.
- Projector: no display is published automatically; runtime visual parity remains a separate browser check.

## Migration and deployment

- Database migration: none.
- Configuration changes: none; `config/config.php` unchanged.
- Deployment target: `develop`; user controls Production deployment.
