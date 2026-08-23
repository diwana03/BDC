# BDC 2.3.3-dev372 — Mobile Projection Remote

## New event tool

- Adds **Mobile Projection Remote** to the J&J Live Screen Projector dashboard.
- Generates one secure event-specific link for the scorer or projector operator.
- Works without an Admin login and expires after 12 hours.
- Regeneration immediately replaces the previous link.

## Mobile controls

- Safe round-aware projector screens: Holding, Judges, Competitors/Finalists, Scoring Status, Live Score Matrix, Callbacks/Finalists and Emcee Live Matching where applicable.
- Previous Page and Next Page.
- Auto Page On and Auto Page Off.
- Hearts, Balloons, Smiling Hearts, Korean Finger Hearts and Clear Effect.
- Live projector screen, page and Auto Page status refreshed every three seconds.

## Security boundaries

- The token is stored as a SHA-256 hash and bound to one projector session, event and Test/Live data mode.
- A Festival remote works only while its own event is active; it cannot control another festival event.
- Server-side allowlists reject result screens and unsupported effects even if a request is manually modified.
- The mobile UI has no result unlocking, Winner Podium, Final Results, publication, screen configuration, theme control, music upload/control, projector-link regeneration or event settings.

## Parity and scoring safety

- The same dashboard and mobile workflow serve isolated Testing and Live modes.
- The existing projector session and display link are reused; no second projector is created.
- Automatic scoring, judge scoring, manual scoring, tier calculation, ties, callbacks and Final Relative Placement are unchanged.

## Validation

- Secure token, 12-hour expiry and event/session binding checks.
- Safe-screen and effect allowlist checks.
- Forbidden result/configuration/music capability checks.
- Festival cross-event protection and exact-session targeting checks.
- Existing Emcee, effects, dashboard parity, judge-order and tie tests.
- Projector and mobile JavaScript syntax compilation.
- `git diff --check`.

Deploy the exact `develop` commit to Staging and test the mobile remote against both a standalone event projector and a shared Festival projector before Production promotion.
