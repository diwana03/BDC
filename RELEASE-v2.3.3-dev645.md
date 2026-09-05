# BDC v2.3.3-dev645 — Integrated projector fullscreen

## Changes

- Turns the existing **BDC · Official Live Display** badge into the projector fullscreen control.
- Fullscreens the outer Live Display document so the complete projection fills the screen rather than only the inner feed frame.
- Supports mouse, touch, Enter and Space; Escape continues to exit through the browser.
- Adds a restrained hover/focus treatment without introducing another audience-facing button or changing the projector layout.
- Rebinds the control after every silent feed swap, including roster page and screen changes.

## Validation

- Candidate/static: focused outer-document fullscreen, badge rebinding, keyboard access, clean-control, cache integration, version JSON, full projector JavaScript suite and repository whitespace checks passed.
- Staging/runtime: Not Runtime-Tested. Deploy this exact `develop` commit to Staging and click the badge in Chrome on the projector computer before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Live Display shell inspected; no scoring workflow change.
- Live Scoring Dashboard: shared Live Display shell inspected; no scoring workflow change.
- Live Scoreboard/projector: shared shell, silent feed swaps, badge state and projector-safe styling statically verified.

## Migration

- No database migration.
