# BDC v2.3.3-dev210

## Manual Scoring event-open recovery

- Prevents Judge Directory initialization or lookup errors from blocking an existing Manual Scoring round.
- Keeps the saved scoring event, competitors, judges, marks and results available when the auxiliary directory is unavailable.
- Adds an explicit idempotent migration for `judge_id` links and indexes on Live and Test scoring judge assignments.
- Retains Judge Database search and new-Judge-ID creation whenever the directory is healthy.

## Safety

- No scoring data is rewritten by the event-open fallback.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
