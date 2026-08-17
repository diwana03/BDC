# BDC v2.3.3-dev251 (Build 2957)

## Projector-safe fireworks

- Builds on the Automatic Final correction and first projector optimisation in dev250.
- Caps sustained fireworks at 260 active particles and two simultaneous rockets.
- Uses a 1× canvas instead of scaling rendering work with the projector device-pixel ratio.
- Limits fireworks painting to approximately 24 frames per second.
- Shortens particle trails, accelerates cleanup and reduces recurring burst frequency.
- Stops creating new fireworks while the projector tab is hidden.
- Keeps the active scoring, callback, ranking or podium screen visible underneath the transparent effect.

## Workflow coverage

- Automatic Finals use **Automatic Final Judge Scoring** after fixed couples are confirmed.
- Manual Finals continue to use manual Relative Placement scoring.
- The workflow correction applies to Test and Live.

## Deployment

- Database migration: none.
- Configuration change: none.
- Release target: `develop` only. Staging and Production are not deployed by this release.
