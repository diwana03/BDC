# BDC 2.3.3-dev444

## Complete server-rendered Dance Cup page

- Replaces the fragile output-buffer composition used by Automatic Dance Cup.
- Always renders event and category details first.
- Always renders Step 1 contestant and judge rosters before live scoring.
- Includes database autocomplete, ordering, removal and Chief Judge controls.
- Includes the existing live judge status, score matrix, checkpoint, ranking, submission and projection workflow below Step 1.
- Does not change scoring calculations or existing database records.

## Validation

- Complete JavaScript regression suite passed.
- Static entrypoint checks confirm the complete page renders before the legacy composition code.
- `git diff --check` passed.
