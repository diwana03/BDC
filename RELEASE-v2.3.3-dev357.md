# BDC 2.3.3-dev357 — Dance Cup Workflow Selector

## Outcome

- Adds a premium Dance Cup landing screen with three direct choices: Manual Scoring, Automatic Scoring and Live Projection.
- Routes saved Dance Cup categories to the manual category workspace, secure judge automation, or separate projector control according to the selected workflow.
- Preserves Dance Cup event/category creation and all existing saved data.
- Keeps Dance Cup tables, judge sessions and projector sessions separate from Jack & Jill.

## Parity Gate

### Candidate/static validation

- Testing Dance Cup dashboard: selector retains `data_mode=test` and routes to isolated `bdc_test_dance_cup_*` workflows.
- Live Dance Cup dashboard: the same selector and shared category routing use real Dance Cup tables.
- Manual scoring: routes to `category.php` with marks, calculation, checkpoints, print and submission unchanged.
- Automatic scoring: routes to `automation.php` with secure judge links and manual fallback unchanged.
- Live projector: routes to the separate `projection-control.php` and Dance Cup projector engine unchanged.
- PHP syntax parsed using the workspace parser; static workflow regression added.
- PHP CLI is unavailable in this workspace, so runtime execution remains part of Staging validation.

### Staging/runtime validation

- Pending deployment of this exact `develop` commit by the user.
- Production promotion remains blocked until the three Test and Live entry choices open the correct saved category workflow on Staging.

## Deployment

- Candidate target: GitHub `develop`.
- User deploys to Staging through Release Manager.
- No Production action performed.
