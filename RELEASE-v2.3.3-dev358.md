# BDC 2.3.3-dev358 — Correct Dance Cup Workflow Routing

## Outcome

- Replaces the shared query-string dashboard illusion with three real Dance Cup workflow dashboards.
- Manual Scoring lists saved categories and opens the organiser scoring sheet.
- Automatic Scoring lists saved categories and opens secure judge automation.
- Live Projection lists saved categories and opens the separate Dance Cup projection controller.
- Moves event and category creation behind a clear `Manage Events & Categories` action.

## Parity Gate

### Candidate/static validation

- Testing dashboard: all three choices retain `data_mode=test` and use isolated Dance Cup tables.
- Live dashboard: the same shared workflow page uses real Dance Cup tables.
- Manual route: `category.php`.
- Automatic route: `automation.php`.
- Projector route: `projection-control.php`.
- Jack & Jill links and tables remain separate.
- PHP syntax parsed with the workspace parser and static workflow regression updated.

### Staging/runtime validation

- Pending deployment of this exact `develop` commit by the user.
- Production promotion remains blocked until all three choices are clicked and verified on Staging.

## Deployment

- Candidate target: GitHub `develop`.
- User deploys to Staging through Release Manager.
- No Production action performed.
