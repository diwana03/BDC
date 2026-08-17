# BDC v2.3.3-dev250 (Build 2956)

## Automatic Final routing and projector performance

- Corrects the pre-pairing instruction on Automatic Finals so it opens **Automatic Final Judge Scoring**, not manual Relative Placement scoring.
- Keeps fixed-couple confirmation mandatory because every Final judge must rank the same confirmed couples.
- Applies the wording correction identically to Test and Live scoring dashboards.
- Caps the fireworks particle pool and simultaneous rockets to prevent performance degrading during sustained effects.
- Renders fireworks at an adaptive canvas resolution and approximately 30 frames per second.
- Reduces expensive glow, trail length, burst size and launch frequency while preserving the cinematic overlay.
- Pauses fireworks painting when the projector tab is hidden.

## Deployment

- Database migration: none.
- Configuration change: none.
- Release target: `develop` only. Staging and Production are not deployed by this release.
