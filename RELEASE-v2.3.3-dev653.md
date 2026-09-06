# BDC v2.3.3-dev653 — Projector name and flag layout

Build 3359

## Changes

- Competitor projector cards now use first name only.
- Makes the name substantially larger while keeping it strictly on one line with responsive ellipsis protection for unusually long names.
- Shows the country flag immediately after the name.
- Makes the flag larger and vertically aligned with the name.
- Removes country-code and country-name text from the competitor identity line.
- Preserves photo, BIB, role panels, page counts, responsive card grid, scoring and data logic.

## Validation

- PHP 8.1 syntax check on the live projector feed.
- Regression assertions for first-name rendering, larger responsive name, larger flag, single-line protection, no country text, and preserved BIB output.
