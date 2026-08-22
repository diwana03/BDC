# BDC 2.3.3-dev355 — Projector Logo Placement

## Outcome

- Removes the duplicate logo injected by the outer Live Display shell.
- Keeps one BDC white-tile logo on information screens and increases it to venue-readable projector scale.
- Centers the logo above the event name on the Holding Screen.
- Preserves compact corner branding on score matrices and other dense projector views.
- Applies through the same shared renderer for isolated Test and Live projection.

## Parity Gate

- Shared Test and Live feed: same renderer and branding assets checked.
- Holding Screen: centered responsive logo/title composition checked.
- Judges, competitors, progress, matrices and results: single native projector identity checked.
- 4:3, 16:9, ultrawide and portrait sizing rules retained through viewport/container units.
- PHP runtime execution remains part of Staging validation because PHP CLI is unavailable in this workspace.

## Deployment

- Candidate target: GitHub `develop`.
- User deploys to Staging through Release Manager.
- No Production action performed.
