# BDC 2.3.3-dev300 · build 3006

## Winner Podium reveal

- Removes the automatic drumroll previously triggered for every progressive podium placement.
- Placement buttons now reveal 5th through 1st immediately without forcing an effect.
- **Show Full Podium** also reveals without triggering an effect.
- Manual Drum Roll and all other Presentation Effects remain available from Projection Control.

## Projector effect sound

- Sends an explicit effect event to the projector sound engine instead of depending only on CSS mutation timing.
- Detects an effect that was already active when the sound script finished loading.
- Queues the current effect when browser audio is still starting and plays it as soon as the projector AudioContext becomes active.
- Refreshes the sound-script cache version so browsers receive the corrected implementation.

## Test and Live parity

- Uses the shared Live Display and Projection Control implementation, so Test and Live receive identical behavior.
