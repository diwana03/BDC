# BDC v2.3.6-dev717

## Scope

- Lists all Test or Live Jack & Jill events across Draft, Published, Completed and Cancelled states, including events without scoring rounds.
- Adds optional name, event-state, round-state and scoring-mode filters.
- Adds approval-gated `stage_event_edit` for allowlisted event and round fields.
- Rejects stale proposals and locks structural round edits after scoring starts.

## Validation

- Focused MCP all-event and approval-gated edit regression: passed.
- Existing MCP, OAuth and event-integration focused regressions: passed.
- Full JavaScript regression inventory: 213 of 249 passed. The remaining 36 failures reproduce legacy version/fixture assertions; this candidate introduced no new failing test and repaired the existing formatting-sensitive OAuth regression.
- PHP 8.1 syntax/runtime gate: pending; PHP CLI is unavailable locally.

## Migration

- No database migration required.

## Deployment

- Local candidate only. Not pushed or merged.
- Production untouched.
