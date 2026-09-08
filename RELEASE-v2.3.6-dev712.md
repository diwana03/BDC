# BDC v2.3.6-dev712

## Adjusted WDC photos on every Dance Cup projection screen

- Uses the saved WDC identity zoom/crop before the shared BDC competitor photo on Contestant Call, All Contestants, Live Scoreboard, and Winner Podium.
- Adds adjusted-photo data to the projector revision so an open audience display refreshes after a photo is changed instead of keeping the previous image.
- Enlarges contestant portraits on All Contestants, Live Scoreboard, and Winner Podium while retaining the 10 percent vertical and 5 percent horizontal safe canvas.
- Keeps backward-compatible shared-photo and minimal feed fallbacks for older workspaces.
- Does not change marks, scores, placements, judging, BDC identities, or SDC identities.

## Validation

- Static WDC projection data-path and cache-revision regression: passed.
- Dance Cup projector identity, scale, pagination, recovery, and universal safe-layout regressions: passed locally.
- PHP syntax: not locally runtime-tested because PHP CLI is unavailable in this workspace; GitHub validation remains required.
- Test deployment: candidate for `develop` only.
- Production: untouched and blocked pending separate Staging runtime approval.
