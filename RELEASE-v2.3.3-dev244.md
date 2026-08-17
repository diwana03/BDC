# BDC 2.3.3-dev244

## Scoring safeguards

- Keeps BDC's existing role tiers and 5/10/15 callback quantities unchanged.
- Calculates Heats and Semifinal panel totals without counting the Chief Judge as an additional ordinary panel vote.
- Retains the Chief mark separately and uses it only to break an otherwise equal callback result.
- Extends the shared Final Relative Placement calculator with a head-to-head mini-contest before the Chief Judge fallback.
- Rejects a Final result that remains exactly tied instead of silently deciding it by database ID.
- Adds regression coverage for Chief-score separation, Leader/Follower parity, duplicate Final ranks and complex Relative Placement ties.

## Scope

- Shared engines serve both Test and Live scoring surfaces.
- No database migration.
- No Production deployment.
