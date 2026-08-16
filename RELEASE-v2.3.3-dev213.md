# BDC v2.3.3-dev213

## Automatic dashboard response fix

- Processes Automatic competitor and setup POST actions before the legacy scoring-page output buffer starts.
- Disables PHP output compression for these small mutation requests.
- Prevents shared-hosting compression from exposing raw encoded bytes in the browser.
- Keeps the database action followed by a clean redirect back to the dedicated Automatic dashboard.

## Safety

- Existing competitors, scores, judge assignments and results are preserved.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
