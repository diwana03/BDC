# BDC 2.3.3-dev352

## Projector branding and adaptive contrast

- Moves the official white-tile BDC logo into the projected stage so it scales with both screen width and height instead of floating as a tiny browser overlay.
- Adds a balanced `BDC · Official Live Display` identity on wide screens and removes it automatically when a narrow aspect ratio needs the space.
- Makes event headings, screen titles, judge assignments, cards, tables, borders, shadows and secondary text adapt to Midnight Burgundy, Obsidian Gold, Ivory Burgundy and Pearl Sapphire.
- Applies the same shared assets to Test and Live projector feeds, including Judges, competitors, progress, matrices, podiums and Final relative placement.

## Validation

- Candidate/static: JSON parsed; stylesheet references, responsive branding injection, four-theme variables and shared projector files checked; repository whitespace gate passed.
- Migration: none.
- Deployment: candidate published to GitHub `develop`; Staging deployment remains with the user.

## Parity Gate

- Testing Score Dashboard: unchanged scoring workflow; verified it continues to use the shared projector session/feed implementation.
- Live Score Dashboard: unchanged scoring workflow; verified it continues to use the same shared projector session/feed implementation.
- Projector: checked `live-display/index.php`, `live-display/feed.php`, `live-display/final-relative-placement.php` and `public/css/projector-themes-v352.css`.
- Runtime/Staging: pending deployment and browser verification across the four backgrounds. Production promotion remains blocked until that pass is complete.
