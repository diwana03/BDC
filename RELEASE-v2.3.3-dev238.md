# BDC 2.3.3-dev238

## Global projector fit-to-screen

- Applies the fit-to-screen rule across the shared Test and Live projector feed.
- Reduces unused outer space while retaining a small bottom safe margin.
- Allows competitor, callback, finalist and judge grids to use the available stage height.
- Expands live score matrices, full-score tables and results tables into the available vertical space.
- Keeps podium and holding displays within the same full-stage layout system.
- Retains automatic pagination when content density would reduce readability.
- Renames split headers to “LEADERS · N COMPETITORS” and “FOLLOWERS · N COMPETITORS”.

## Validation and deployment

- Static checks completed for global stage spacing, split grids and table-height rules.
- Shared Test and Live projector rendering updated together.
- Full-screen visual validation is required on Staging at multiple projector resolutions.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
