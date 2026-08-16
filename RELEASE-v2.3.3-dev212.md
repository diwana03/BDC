# BDC v2.3.3-dev212

## Automatic competitor persistence fix

- Restricts the Automatic dashboard forwarding redirect to GET navigation.
- Allows competitor, bib, removal and tier POST actions to reach their database handlers.
- Returns successful actions to the same Automatic dashboard, where the updated competitor list and live matrix reload immediately.

## Safety

- Existing competitors, scores, judge assignments and results are preserved.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
