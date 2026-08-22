# BDC v2.3.3-dev341

## Mobile judge header correction

- Moves the appearance selector into the completed judge header after branding initialization, eliminating the timing race found on the deployed dev340 page.
- Keeps the selector inside the metadata row instead of floating over the title or scoring controls.
- Corrects the sub-390 px logo column from 52 px to the real 58 px white-tile width, preventing logo/title overlap.
- Refreshes both branding and theme asset versions so mobile browsers cannot retain the broken layout.

## Parity Gate

- Testing Score Dashboard: Test criteria, Heats/Semifinal and Final share the corrected initialization and sizing.
- Live Scoring Dashboard: Live criteria and shared Heats/Semifinal/Final use the same corrected assets.
- Projector: unchanged.

## Validation

- Live dev340 inspection confirmed the header itself and exposed the initialization race and 390 px grid mismatch corrected here.
- JavaScript syntax, static docking timing, Test/Live cache parity, `git diff --check` and JSON parsing passed.
- PHP CLI is unavailable; this exact candidate requires deployment to Staging for final mobile browser validation.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys the exact commit to Staging.
- Production: unchanged and blocked pending Staging validation.
