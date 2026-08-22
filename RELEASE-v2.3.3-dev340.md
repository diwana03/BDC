# BDC v2.3.3-dev340

## Judge header runtime correction

- Docks the Light, Dark and System selector inside the judge header metadata row.
- Prevents the appearance selector from covering long event titles on desktop.
- Prevents the selector from covering sticky scoring and submit controls on mobile.
- Keeps the mobile selector compact as three icon buttons.

## Parity Gate

- Testing Score Dashboard: shared appearance loader docks into Test criteria, Heats/Semifinal and Final judge headers.
- Live Scoring Dashboard: the same loader docks into Live criteria and shared Heats/Semifinal/Final judge headers.
- Projector: unchanged because the projector has no judge appearance selector.

## Validation

- The deployed dev339 URL confirmed the aligned logo, title and metadata, and exposed the floating-selector overlap corrected here.
- JavaScript syntax, static docking markers, cache parity, `git diff --check` and JSON parsing passed.
- PHP CLI is unavailable in this workspace; final runtime verification requires this exact candidate on Staging.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys the exact commit to Staging.
- Production: unchanged and blocked pending Staging runtime approval.
