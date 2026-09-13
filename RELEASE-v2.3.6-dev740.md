# BDC 2.3.6 dev740

## Dance Cup tie-safe podium reveal

- Fixes the misleading Dance Cup podium state where equal scores could produce `1st, 2nd, 2nd`, leaving no official third-place row for the Reveal 3rd Place command.
- Blocks result unlocking and every podium reveal while an exact-score tie still requires the Chief Judge final order.
- Shows the operator a clear warning and a direct link to the existing score-preserving tie-resolution workflow.
- Independently redacts unresolved winner data in the projection feed and forces any already-open unresolved results or podium screen back to Holding.
- After the Chief Judge orders the tied contestants, the unchanged scores display with unique official placements such as 1st, 2nd and 3rd.
- Applies identically to isolated Test and Live Dance Cup tables through the existing data-mode switch.
- Preserves contestant photos, duet/team names, flags, countries, responsive layouts and single-champion centering.

## Parity Gate

### Candidate and static validation

- Test and Live projection control: shared exact-score tie guard checked.
- Test and Live projection feed: unresolved winner redaction checked.
- Audience projector: unresolved result and podium states fall back to Holding.
- Chief Judge resolution: existing score-preserving placement update remains authoritative.
- Focused regression: `tests/dance-cup-tie-safe-podium-v740.js`.
- Full JavaScript regression suite: 271 passed, 0 failed.
- Environment-blocked PHP wrapper checks: 2 (`canonical-country-normalization-v625.js` and `projection-diagnostics-v638.js`) because PHP CLI is unavailable in this workspace.

### Staging and runtime validation

- Not runtime-tested on Staging yet.
- Production promotion remains blocked until this exact `develop` commit is deployed to Staging and tested with equal-score Dance Cup entries.

## Migration and deployment

- Database migration: none.
- Deployment status: workspace candidate only. Not pushed and not deployed.
