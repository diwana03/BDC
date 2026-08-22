# BDC v2.3.3-dev343

## Premium scoring matrix

- Replaces the basic pastel Leader/Follower matrix with premium BDC navy, burgundy, champagne and warm-ivory surfaces.
- Adds semantic matrix colors matching judge controls: green YES, gold A1, slate A2 and bronze A3.
- Improves table hierarchy, sticky placement/competitor columns, row hover, borders, spacing, shadows and scrollbars.
- Includes a coordinated Dark theme while preserving Light as the default.
- Preserves score values, calculation, sorting, judge order, sticky columns and horizontal scrolling.

## Parity Gate

- Testing Score Dashboard: isolated Automatic Heats/Semifinal matrix loads the shared premium CSS and mark decorator.
- Live Scoring Dashboard: real Automatic Heats/Semifinal matrix loads the same shared premium CSS and mark decorator.
- Projector: unchanged because the admin matrix does not feed or render the audience display.

## Validation

- Shared CSS/JavaScript asset references, Test/Live parity, JavaScript syntax, JSON parsing and `git diff --check` passed.
- PHP CLI is unavailable; this exact candidate requires deployment to Staging for final browser validation.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys the exact commit to Staging.
- Production: unchanged and blocked pending Staging validation.
