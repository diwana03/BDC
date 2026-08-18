# BDC 2.3.3-dev284 · Build 2990

## Test + Live Automatic Judge Control parity

- Fixes the Judge Live Scoring HTTP 404 route across both Testing and Live scoring.
- Keeps the dev283 Test Automatic screen gateway.
- Adds a Live Automatic gateway through the existing scoring `index.php` route.
- Uses the Live gateway for Heats, Semifinal and Final judge-control iframes and full-control links.
- Keeps Test-only random generators isolated from Live while sharing the same stable routing strategy.

## Validation

- Added regression coverage for the Live gateway, shared judge-control include, Heats/Semifinal iframe and Final iframe.
- Confirmed whitespace-safe patch construction with `git diff --check`.
- PHP CLI is unavailable in this workspace; deployment PHP validation remains required on Staging.

## Deployment

- Database migration: none.
- Push target: `develop`.
- Production deployment: not performed.
