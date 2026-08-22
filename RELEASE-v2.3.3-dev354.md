# BDC 2.3.3-dev354 — Dance Cup Automation & Event Projection

## Outcome

- Adds a dedicated Dance Cup Automatic Scoring workspace. It does not use Jack & Jill rounds, judge sessions, projector sessions, marks, or routes.
- Creates one secure browser-scoring link per assigned Dance Cup judge with draft saving, submission lock, progress, regeneration and scorer-controlled reopening.
- Keeps the current Dance Cup manual judging sheet, calculations, checkpoints, print workflow and submit action as the operational fallback.
- Adds a separate event-wide Dance Cup projector link with Holding, Judges, Contestants, Scoring Progress, Live Scoreboard and Winner Podium screens.
- Renames the public Dance Cup identifier to **Contestant Number** while retaining the existing database column for backward-compatible storage.
- Adds a Run of Show that alternates Contestant Number → customized Holding Screen → next contestant using configurable display durations.
- Allows instant category switching within the same Dance Cup event while preserving the same projector URL.
- Includes four adaptive premium projector backgrounds: Midnight Wine, Obsidian Gold, Ivory Wine and Pearl Navy.

## Data isolation and migration

- Live tables: `bdc_dance_cup_judge_sessions`, `bdc_dance_cup_event_projection`.
- Test tables: `bdc_test_dance_cup_judge_sessions`, `bdc_test_dance_cup_event_projection`.
- Migration: `database/migrations/20260823_1800_dance_cup_automation_projection.php`.
- Existing Dance Cup competitors, judges, criteria, marks, results and checkpoints are preserved.
- No Jack & Jill table is read or changed by the new Dance Cup module.

## Parity Gate

### Candidate/static validation

- Testing Dance Cup dashboard and isolated `bdc_test_dance_cup_*` workflow: checked in shared module routing and schema provisioning.
- Live Dance Cup dashboard and real `bdc_dance_cup_*` workflow: checked in the same shared services and UI.
- Dance Cup judge screen: secure token lookup, save draft, required-score validation, submission lock and reopen compatibility checked.
- Dance Cup projector: event category switching, contestant-number screen, holding cycle, judges, progress, scores, podium and four themes checked.
- Jack & Jill dashboard/projector: confirmed untouched by this release.
- Static regression: `tests/dance-cup-automation-projection-v354.php` added; JSON and JavaScript checks completed where local runtimes were available.
- PHP CLI is unavailable in this workspace, so PHP runtime execution remains part of Staging validation.

### Staging/runtime validation

- Pending deployment of this exact `develop` commit by the user.
- Production promotion is blocked until Staging validates Test and Live judge submissions, category switching, Run of Show timing, projector responsiveness, calculation, checkpoint, manual fallback and submitted state.

## Deployment

- Candidate target: GitHub `develop`.
- User deploys to Staging through Release Manager.
- No Production action performed.
