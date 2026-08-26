# BDC v2.3.3-dev423

Build: 3129  
Date: 2026-08-26

## Clean Automatic setup

- Keeps one compact BDC header with Dashboard and Theme actions, removing the duplicate Back controls that overlapped the page.
- Uses stable responsive grids for contestant number, competitor search, judge search, Chief selection and Add actions.
- Keeps competitor and judge suggestions anchored below their own field and raises the active card so results are not clipped by adjacent panels.
- Keeps the direct Live Projection action introduced in dev422 and preserves Test/Live mode in its route.

## Validation

- All 56 locally executable Node regressions passed.
- Test and Live use the same layout and directory client.
- Scoring calculations, judge marks, rankings and projector state are unchanged.
- PHP syntax and authenticated browser runtime validation remain required on Staging.

## Deployment

- Publish to the authorized public `diwana03/BDC` `develop` branch.
- Production promotion remains blocked until Staging validates desktop/mobile layout, both directory searches and Live Projection navigation.
