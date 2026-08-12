# BDC 2.3.3-dev165

Projector podium-loop and timing correction.

## Fixes

- Winner Podium always uses **Show Full Podium** when reached through a loop.
- Loop timing now uses `TIMESTAMPDIFF` with the database clock, avoiding PHP/database timezone drift.
- The selected delay is measured from the last displayed loop tab.
- Switching through Holding, Judges, or other safe tabs no longer clears Results Unlock and removes the podium from the loop.
- The explicit **Lock** button remains the only operation that clears results reveal permission.
- Applies to Testing and Live.

## Deployment

- Branch: `develop`
- No historical migration was changed.
- No Production configuration was changed.
