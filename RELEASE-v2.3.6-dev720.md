# BDC v2.3.6-dev720

## Scope

- Restores the actual dev717 all-state event listing and approval-gated event-edit implementation onto the current release line.
- Adds `list_event_roster` with exact active council identities, roles and bib assignments.
- Adds `stage_division_roster_sync` to merge new registered division competitors and sequentially reassign every active Lead and Follow bib.
- Preserves existing active roster members; this operation never removes competitors.
- Rejects range overflow, duplicate identities or bibs, scoring-started rounds and roster changes made after staging.
- Keeps all Live changes pending until Super Admin approval in Event Integration Review.

## Validation

- Focused MCP roster-sync safety regression: passed.
- Existing MCP, OAuth, event integration and completed-event duplication regressions: passed.
- Full JavaScript inventory: passed except two PHP-backed checks that require the GitHub PHP 8.1 runtime.
- PHP 8.1 runtime gate: pending because PHP CLI is unavailable locally.

## Migration

- No database migration required.

## Deployment

- Local candidate only. Not pushed or deployed.
- Production remains untouched.
