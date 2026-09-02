# v2.3.3-dev575

## WDC shared photo preview

- Displays the existing shared-person photo when a solo WDC identity has no dedicated WDC photo.
- Uses a separate guarded profile lookup instead of the join removed during the HTTP 500 recovery.
- A replacement upload still updates only the WDC identity photo.

## Safety

- No migration or automatic data change.
- BDC and SDC photos are read as a fallback but never modified by this page.

## Validation

- Full JavaScript regression suite: required before publishing.
- Runtime preview and upload verification: required after deployment.
