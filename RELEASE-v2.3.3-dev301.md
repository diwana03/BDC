# BDC 2.3.3-dev301 · build 3007

## Projector control regression

- Restores reliable Projection Control and presentation-effect button operation.
- Removes the direct audio-event hook from the visual projector renderer.
- Keeps the sound engine isolated so browser audio startup or failure cannot interrupt projector visuals or controls.

## Sound and podium behavior retained

- Sound still detects the active effect through the established projector effect state.
- Sound that arrives while browser audio is starting remains queued and plays after audio becomes active.
- Progressive Winner Podium and **Show Full Podium** remain free of automatic effects.
- Manual Presentation Effects remain available.

## Test and Live parity

- Uses the shared Projection Control and Live Display implementation for identical Test and Live behavior.
