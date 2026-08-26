# BDC v2.3.3-dev420

Build: 3126  
Date: 2026-08-26

## Theme and navigation overlap

- Fixes the shared Theme selector covering Back to Dashboard and other top-right controls.
- Pages with a premium admin or judge header continue to dock Theme inside that header.
- Pages without a dockable header now receive a reserved responsive appearance toolbar above page content.
- The fallback toolbar is part of document flow, so it cannot cover links, buttons, forms or scoring controls.
- Removes the temporary fallback row automatically when the premium header subsequently docks Theme.

## Connected surfaces

- Shared Admin dashboard header.
- Testing and Live Jack & Jill scoring dashboards.
- Testing and Live judge scoring pages.
- Dance Cup setup, category, judge, automation and projection-control pages.
- Live projection control workspaces.
- Audience projector rendering and scoring calculations are unchanged.

## Validation

- Shared fallback placement and responsive CSS regression: passed.
- Premium admin and judge header docking cleanup regression: passed.
- Theme cache-busting across every active Theme entry point: passed.
- PHP syntax checks: not locally run because PHP is unavailable in this workspace; required on Staging.
- Browser runtime: not locally tested; required on Staging at desktop and mobile widths.

## Parity Gate

- Testing Score Dashboard: candidate/static validation only.
- Live Score Dashboard: candidate/static validation only.
- Test and Live judge surfaces: candidate/static validation only.
- Projector controls: candidate/static validation only; audience output logic unchanged.
- Staging runtime validation remains required before Production promotion.

## Deployment

- Candidate published to the `develop` release line after all locally available static checks passed.
- Production promotion remains blocked until Staging confirms Theme and Back to Dashboard remain separately visible and clickable on affected desktop and mobile pages.
