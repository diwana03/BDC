# BDC 2.3.3-dev302 · build 3008

## Projector sound reliability

- Adds a one-time **Start Projector** gate when Projection Control opens the sound-enabled projector.
- The required operator click satisfies browser audio security and removes the gate immediately after sound activates.
- Replays an effect that arrived while browser audio was still suspended.
- Raises controlled output gain for clearer presentation sound without changing effect timing.
- Leaves **Open Muted** and all audience-facing projection screens unchanged.
- Applies through the shared Test and Live projection engine.
- Keeps podium reveals free of automatic effects.
