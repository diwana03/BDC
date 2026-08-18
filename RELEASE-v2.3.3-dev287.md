# BDC 2.3.3-dev287 · Build 2993

## Final Relative Placement Matrix layout

- Applies to the shared Test and Live projector feed.
- Removes the repeated `LIVE · PROVISIONAL` subtitle below the main matrix title.
- Formats pair identifiers as `#P1 · Names`, `#P2 · Names`, and onward.
- Uses a balanced fixed-width layout: 14% Result, 42% Couple, and the remaining width shared evenly by judges.
- Centres result labels and left-aligns couple information with consistent spacing.
- Preserves Champion, 1st Runner-Up, 2nd Runner-Up and later ordinal result labels.
- Keeps the Final SUM column removed.

## Deployment

- Database migration: none.
- Push target: `develop`.
- Production deployment: not performed.
