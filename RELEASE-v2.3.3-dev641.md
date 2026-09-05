# BDC v2.3.3-dev641 — Projector identity and Full Screen recovery

## Changes

- Groups each competitor name, flag and full country label into one structural right-side identity block so refreshes cannot place the country under the portrait.
- Eliminates the live shell's stale duplicate roster stylesheet, which previously overrode the upgraded card layout after deployment.
- Enlarges responsive first names and flags while retaining fifteen Leaders and fifteen Followers per page.
- Keeps long judge names on one line at a responsive size instead of splitting a name inside the word.
- Restores a prominent Full Screen button in the Live Projection Control header and retains the embedded control button.
- Does not add a control to the audience display or change competitors, judges, scores, links, tokens or round assignments.

## Validation

- Candidate/static: focused structural-card and control assertions, all projector/projection JavaScript tests, version JSON and repository whitespace checks.
- Staging/runtime: Not Runtime-Tested. Deploy this exact commit to Staging and inspect Competitors, Judges and the Live Projection Control at 16:9 before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Testing projection workspace and feed paths checked statically.
- Live Scoring Dashboard: shared Live projection workspace and feed paths checked statically.
- Live Scoreboard/projector: structural competitor identity, responsive judge name, stylesheet cache and audience-control separation checked statically.

## Migration

- No database migration.
