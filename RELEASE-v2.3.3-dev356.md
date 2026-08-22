# BDC 2.3.3-dev356 — Final Duplicate Projector Logo Fix

## Outcome

- Excludes both `/live-display/` and `/live-display/index.php` from portal-wide floating-logo injection.
- Keeps the single large native projector logo shown inside the event presentation.
- Cache-bumps the global branding script so Staging browsers receive the correction immediately.

## Parity Gate

- Shared Test and Live projector shell routes use the same exclusion.
- Shared projector feed retains its venue-scale logo and Holding Screen composition.
- JavaScript syntax and PHP static parsing checked.
- PHP runtime execution remains part of Staging validation because PHP CLI is unavailable in this workspace.

## Deployment

- Candidate target: GitHub `develop`.
- User deploys to Staging through Release Manager.
- No Production action performed.
