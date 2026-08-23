# BDC 2.3.3-dev361 — Competitor Photo Adjustment

## Outcome

- Adds `Replace photo` directly above the competitor crop frame.
- Adds `Adjust or replace photo` to every saved competitor edit screen.
- Makes dragging smoother with animation-frame rendering and reliable mouse, pen and touch pointer handling.
- Stops browser image dragging and recovers cleanly after cancelled or lost pointer gestures.
- Constrains movement so the image cannot be dragged beyond the crop frame and expose empty space.
- Treats the newest upload as the preserved original for every later adjustment.

## Validation

- PHP parsing passed for competitor edit and photo adjustment.
- Static regression checks passed for upload validation, CSRF protection, audit events, original-photo preservation, responsive cropping and interrupted-pointer recovery.
- Database migration: none.

## Scoring Parity Gate

- Testing dashboard: unaffected.
- Live dashboard: unaffected.
- Projector: competitor photo URLs remain compatible and projection rendering is unaffected.
- Staging browser verification remains pending deployment by the user.

## Deployment

- Candidate target: GitHub `develop`.
- No Production action performed.
