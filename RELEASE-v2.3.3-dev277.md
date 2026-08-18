# BDC 2.3.3-dev277 · Build 2983

## Test division restoration

- Restores the Test dashboard Division workflow after the Bachata Rising correction.
- Loads `SpecialCategoryService` before validating the selected Test category.
- Extends the regression guard to fail if the required service import is removed.

## Parity Gate

- **Testing Score Dashboard:** class dependency, Bachata Rising selection, invalid-category rejection, and exact selected-division persistence statically checked.
- **Live Scoring Dashboard:** dedicated special-category route and persistence remain unchanged and were statically checked.
- **Projector / Live Scoreboard:** reads the saved round division and requires no rendering change; Test and Live selection sources remain intact.

## Validation

- Candidate/static: JSON validation, whitespace validation, dependency/import inspection, Test selection regression inspection, and Live special-category route inspection completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
