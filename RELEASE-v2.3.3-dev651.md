# BDC v2.3.3-dev651 — responsive finalist couple projector grid

Build 3357

## Fix

- Finalist Couples and Emcee Random Final Match now use the same fixed five-card row basis.
- 1–5 couples display as one centered row.
- 6–10 couples display as two rows.
- 11–15 couples display as three rows.
- Incomplete final rows keep the exact same card width and are centered instead of stretching.
- Card sizing is constrained so BIB/name content stays inside the card.
- Existing BDC logo, header, BDC Official Live Display badge, scoring, matching and result logic are untouched.

## Validation

- PHP 8.1 syntax check on live-display/feed.php.
- Focused regression checks five-column basis, no flex-grow stretching, centered incomplete rows, and unchanged official branding references.
- No database migration.
