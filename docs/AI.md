# AI Engineering Guide

Read `PROJECT.md` and this file before changing the repository.

## Non-negotiable rules

- Do not run schema mutations from public or admin HTTP requests.
- Add ordered, immutable migrations under `database/migrations` and run them through `bin/migrate.php`.
- A migration that delegates to another file must list that file as a checksum dependency; applied migrations and their dependencies are immutable.
- Keep server-side calculations authoritative. Browser calculations are previews only.
- Use PDO prepared statements for all request-derived values.
- Require CSRF validation for state-changing browser requests.
- Escape rendered data with `e()`.
- Keep uploads outside public paths unless the file is intentionally published.
- Preserve scoring audit records and publication traceability.
- Use database transactions and row locks for capacity, publication, merging, and other multi-row invariants.
- Do not restore web installers, patch scripts, or rollback scripts.

## Release boundaries

Version 2.2.0 is a stabilization and security release. Do not consolidate or redesign scoring behavior in this release. That work belongs to 2.3.0 and must retain golden fixtures from real competitions.

## Verification

Every change must include the narrowest relevant automated check. Database changes must be tested on both a blank schema and a copy of the previous release schema. Never mark an upgrade complete until the migration runner is idempotent.
