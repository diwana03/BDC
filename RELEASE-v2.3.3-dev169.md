# BDC 2.3.3-dev169

## Cinematic projector effects

- Replaces emoji-based effects with a hardware-accelerated full-screen canvas engine.
- Fireworks now use launch trails, glowing multi-colour bursts, particle trails, gravity and staggered timing.
- Confetti now uses hundreds of individually rotating pieces with depth, drift and natural fall movement.
- Drum Roll now uses moving stage lights, radial shockwaves, a pulsing reveal core and cinematic vignette.
- Removes the hard-coded `RANDOM MATCH` message from the general Drum Roll effect.

## Test and Live parity

- The effect engine is shared by Test and Live projector links.
- Validate each effect from **Test Live Presentation** before using the identical controls on Live.
- No database migration is required.
