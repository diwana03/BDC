# BDC 2.3.3-dev452 · build 3158

## Make the single Projection workflow visible and actionable

- Moves the only Live Projection card directly below the six workflow steps.
- Makes Step 6 Projection open the actual Projection Control in a new tab.
- Keeps current screen, auto cycle state, Projection Control, Projector Screen and link regeneration in one visible card.
- Does not reintroduce any duplicate Projection controls.
- Changes no projector state, polling, scoring calculation or database schema.

## Parity Gate

- Testing and Live Automatic Dance Cup use the same shared workspace and retain their isolated data-mode URLs.
- Projection Control and audience projector endpoints remain unchanged.
- The restored Jack and Jill scoring core remains checksum-protected and unchanged.

## Validation

- Focused visibility, direct-link and single-card regression checks passed.
- Complete JavaScript regression suite passed.
- PHP/browser runtime remains required on Staging.
