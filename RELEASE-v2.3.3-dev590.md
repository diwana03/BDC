# BDC v2.3.3-dev590

## MCP division competitor listing repair

- Corrects the MCP directory call that passed a division name into the eligibility service's role parameter and returned `Invalid Jack & Jill role`.
- Applies the same approved-history division eligibility service used by final Event Integration approval, preventing listing and approval from disagreeing.
- Returns each competitor's exact eligible leader or follower roles and preserves optional name and role filters.

## Validation

- Focused division-directory, OAuth, MCP and Release Manager safety checks pass locally.
- PHP 8.1 lint and the complete JavaScript regression workflow must pass before merge.
- No database migration or data mutation.
