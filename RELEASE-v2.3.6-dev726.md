# BDC 2.3.6-dev726

## Change

- Uses the same saved 4:5 rounded-rectangle photo preview on J&J competitor, matching, finalist, podium and judge projector screens for both Salsa and Bachata.
- Uses that same preview on Dance Cup Contestant Call, All Contestants, Live Scoreboard, Winner Podium and judge screens.
- Removes the Dance Cup projector's hidden additional `scale(1.16)` crop and projector-only face offset.
- Adds **Reset View** to the J&J competitor, Dance Cup contestant and judge photo studios, clearing unsaved zoom and position.
- Adds **Restore Original Photo** to all three editors for authorized profile editors. Background removal remains Super Admin-only.
- Adds an idempotent Dance Cup original-photo column and backfills each existing current photo as its safe restore baseline. Existing photos cannot recover an older source that was discarded before this release, but every later replacement and crop is reversible.
- Applies identically to Test and Live renderers. No scoring, placement, registration or result data is changed.

## Validation

- Static focused checks cover J&J and Dance Cup projector 4:5 preview parity, removal of hidden projector zoom, Reset View, Restore Original, the Dance Cup schema migration and profile-integration preservation.
- Existing judge projector sizing checks were updated to require large 4:5 portraits while retaining the 4×2 eight-judge grid and 10% vertical / 5% horizontal safe area.
- PHP runtime is unavailable in this workspace, so PHP syntax and browser rendering remain Not Runtime-Tested until the exact candidate is deployed to Test.
- Deployment: Test candidate for `develop` only. Production is unchanged.
