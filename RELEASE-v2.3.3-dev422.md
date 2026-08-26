# BDC v2.3.3-dev422

Build: 3128  
Date: 2026-08-26

## Dance Cup Automatic setup UX

- Cleans the Contestant and Judge database autocomplete dropdowns so they align directly below the search field, use consistent spacing, readable primary and secondary text, bounded height, scrolling and visible hover/focus states.
- Keeps the same database-backed selection behavior for both contestant and judge searches.
- Preserves canonical BDC Competitor and Judge Database links and the existing duplicate-assignment protection.
- Keeps the same implementation for isolated Test and real Live Dance Cup categories.

## Live Projection access

- Restores a direct Live Projection action on the Dance Cup Automatic setup screen.
- Routes the action into the existing Dance Cup projection control for the current category.
- Preserves `data_mode=test` when working in isolated Test mode and uses Live data otherwise.
- Does not alter scoring calculations, judge marks, ranking logic or projector state behavior.

## Validation

- Added `tests/dance-cup-automatic-setup-ux-v422.js` covering both directory fields, clean dropdown structure, scroll/stacking behavior and Live Projection routing.
- Existing Test/Live data isolation remains unchanged.
- Existing Automatic Judge Links & Progress workflow remains unchanged.
- PHP runtime/browser validation is still required through the normal Release Manager staging flow.

## Deployment

- R&D release pushed to public `develop`.
- No direct Production edit or deployment performed.
- Production promotion remains through Release Manager after staging validation.
