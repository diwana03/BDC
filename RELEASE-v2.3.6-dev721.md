# BDC v2.3.6-dev721

## Scope

- Adds `stage_competitor_removals` for recoverable withdrawal of exact active Jack & Jill entries.
- Adds `stage_competitor_bib_updates` for atomic bib amendments and bib swaps.
- Keeps Test and Live operations scoped to an exact event and round and pending until Super Admin approval.
- Rejects scoring-started rounds, stale rosters, cross-round entries, duplicate entry requests and role-specific bib collisions.
- Leaves Salsa, Bachata and Open division selection unchanged.

## Parity Gate

- Testing Score Dashboard: shared `bdc_test_scoring_entries` write path statically verified.
- Live Score Dashboard: shared `bdc_scoring_entries` write path statically verified.
- Live projector: active-entry and bib consumers remain unchanged; database polling receives approved changes.
- Staging runtime approval, refresh and projector verification: not yet run and blocks Production promotion.

## Validation

- Focused MCP roster-amendment regression: passed.
- Existing MCP, OAuth, event integration and roster-sync regressions: passed.
- Full JavaScript inventory: all JavaScript-only checks passed; two PHP-backed checks could not run without PHP CLI.
- PHP 8.1 runtime gate: pending because PHP CLI is unavailable locally.

## Migration

- No database migration required.

## Deployment

- Local candidate only. Not pushed or deployed.
- Production remains untouched.
