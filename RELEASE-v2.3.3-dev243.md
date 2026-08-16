# BDC 2.3.3-dev243

## BDC Final entry enforcement

- Hides the direct-add competitor panel from Finals created from Heats or Semifinal callbacks.
- Rejects forged direct-add requests for callback-derived Finals on the server.
- Removes Add Next Ranked controls from Test and Live Final dashboards.
- Keeps direct entry available only when the event intentionally starts with a Final and has no source round.
- Does not alter existing callback results, pairs or scores.

## Deployment

- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
