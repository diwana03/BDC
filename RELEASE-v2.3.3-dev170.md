# BDC 2.3.3-dev170

## True projector overlays

- Presentation effects now use a forced top-level transparent layer above the active projector feed.
- The scoring, matching, holding, results, or podium screen remains visible underneath.
- Effect commands no longer reload the underlying projector feed.
- Fireworks begin with two immediate cinematic bursts, followed by launch trails and staggered aerial bursts.

## Additional premium effects

- **Gold Celebration**: reflective metallic streamers with glow and natural drift.
- **Laser Sweep**: multi-colour moving stage beams using additive light blending.
- **Champion Impact**: central gold impact burst, expanding shockwave, and glowing particles.
- Existing Drum Roll, Cinematic Fireworks, and Celebration Confetti remain available.

## Test and Live parity

- Testing and Live use the same controller, action endpoint, service allow-list, and projector renderer.
- Validate all six effects from Test Live Presentation before using them on Live.
- No database migration is required.
