# BDC v2.3.3-dev638

## Feature

- Adds the OAuth-protected read-only `diagnose_projection` MCP tool.
- Supports Jack & Jill and Dance Cup projections in isolated Test and Live data modes.
- Checks the exact event and round or category relationship, current display session, active screen, roster totals, role counts, bib completeness and duplicates.
- Audits competitor and judge profile links, photos, country values and flag resolution.
- Checks Jack & Jill Flight Rounds, results, final pairs and expected competitor, score-matrix and judge-call pagination.
- Checks Dance Cup criteria, marks, results, active contestant and active category integrity.
- Detects result-screen and Results Reveal lock inconsistencies.
- Verifies that the real projection runtime files are readable.
- Never changes projection state or scoring data and never returns projector access tokens.
- Reports the current release version during the MCP initialize handshake instead of the stale dev592 value.

## Security

- Existing Super Admin OAuth authorization is required.
- Existing `bdc.events.read` scope is required.
- Tool annotations declare read-only, non-destructive and idempotent behavior.
- Diagnostic errors are truncated and do not include credentials or projector tokens.

## Validation

- Pre-change baseline: the MCP connector exposed roster and approval tools only; no projection diagnostic tool or read-only projector integrity response existed.
- Projection diagnostics behavioral and integration regression: pending.
- Full PHP 8.1 lint and JavaScript regression gate: pending.
- Staging Test Jack & Jill runtime: not tested.
- Staging Live Jack & Jill runtime: not tested.
- Staging Test Dance Cup runtime: not tested.
- Staging Live Dance Cup runtime: not tested.
- Production promotion is blocked until Staging runtime verification passes.

## Parity Gate

- Testing Score Dashboard and isolated `bdc_test_*` projection tables: statically covered.
- Live Scoring Dashboard and real projection tables: statically covered.
- Shared Jack & Jill projector: `live-display/index.php`, `live-display/state.php` and `live-display/feed.php`.
- Dance Cup projector: `admin/dance-cup/projector.php` and `admin/dance-cup/projection-feed.php`.
- Candidate/static validation: pending.
- Staging/runtime validation: required and not yet performed.

## Database

- No migration.
- No schema or data writes.
- No scores, marks, results, rosters, judges, bibs, sessions, effects or reveal state are changed.

## Deployment

- Source candidate only.
- After all mandatory gates pass, deploy the merged `develop` commit to Staging through Release Manager.
- Do not promote to Production until all four Staging diagnostic paths and the relevant visual screens are verified.
