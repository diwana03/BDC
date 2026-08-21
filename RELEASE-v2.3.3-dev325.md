# BDC v2.3.3-dev325

## Dark-theme cache hotfix

- Corrects the deployment issue that left browsers loading cached dev323 theme JavaScript and CSS after dev324.
- Updates the theme asset version on every Admin, Test, Live, judge, Dance Cup and projection-control entry point.
- Updates the shared scoring stylesheet import and the theme controller's dynamically loaded stylesheet URL.
- Makes the dev324 contrast correction and Light first-time default immediately discoverable by the browser without a manual cache clear.

## Validation

- Candidate/static: no `bdc-theme.js?v=323` or `bdc-theme.css?v=323` references remain.
- Candidate/static: all 12 theme entry points request dev325 assets.
- Candidate/static: JavaScript syntax, JSON, CSS structure and whitespace checks passed.
- Candidate/static: all 12 core dark-theme colour pairs still meet at least 7.04:1 contrast.
- Database migration: not required.
- Staging/runtime: pending user deployment of this exact `develop` commit.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard, selector, automatic wrapper and Test judge paths checked.
- Live dashboard and Live judge paths checked.
- Dance Cup dashboard/category and Admin dashboard checked.
- Shared projection workspace and projection control checked.
- Audience projector remains independent and unchanged.
