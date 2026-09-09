# BDC v2.3.6-dev723

## Cached MCP client withdrawal bridge

- Adds a narrowly scoped compatibility path to the already-published competitor staging action so a cached ChatGPT connector can stage withdrawal of every active competitor from one exact draft Jack & Jill round.
- Requires the exact `WITHDRAW-ALL-ACTIVE` confirmation marker and a `withdraw-all-active:` source key before the compatibility path can run.
- Reads the active roster server-side and submits its exact entry IDs through the existing approval-gated removal workflow.
- Preserves scoring-start locks, stale-roster fingerprint checks, recoverable `withdrawn` status, idempotent batch keys and Super Admin Integration Review.
- Does not delete or edit the event, round, judges, scores, results, archived rounds or competitor identities.

## Validation

- Cached-client withdrawal bridge regression: passed locally.
- Existing MCP roster amendment, tool discovery, connector, OAuth and event integration regressions: passed locally.
- PHP syntax: not locally runtime-tested because PHP CLI is unavailable in this workspace; GitHub validation remains required.
- Test and Live parity: shared MCP and event integration services cover isolated Test and Live tables through the existing `data_mode` routing.
- Projector: unaffected because the release stages a proposal only and changes no roster until Super Admin approval.
- Staging runtime: not tested. Production promotion remains blocked until this exact candidate passes Staging runtime verification.
