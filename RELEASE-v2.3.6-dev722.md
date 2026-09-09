# BDC v2.3.6-dev722

## Scope

- Advertises MCP tool-list changes so connected clients can refresh newly deployed actions.
- Restores discovery of the existing approval-gated competitor withdrawal and bib amendment tools.
- Does not change events, rounds, competitors, scores or approval records.

## Validation

- MCP discovery regression verifies `tools.listChanged` is enabled.
- Existing roster amendment regression remains required.

## Migration

- No database migration required.
