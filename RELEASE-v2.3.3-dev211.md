# BDC v2.3.3-dev211

## Automatic dashboard action recovery

- Fixes Add Existing and Create Name & Add competitor actions that previously posted to an unhandled route.
- Fixes bib updates, competitor removal and tier settings on the same dashboard.
- Redirects completed actions back to the live Automatic dashboard and displays success or validation errors.
- Keeps Judge Directory suggestions fail-open so an auxiliary lookup cannot block competition operations.
- Adds CSRF enforcement to Registration Desk link regeneration.

## Safety

- Existing competitors, scores, judge assignments and results are preserved.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
