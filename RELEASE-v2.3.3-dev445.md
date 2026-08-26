# BDC 2.3.3-dev445 · build 3151

## Automatic Dance Cup workflow completion

- Adds explicit roster validation and a **Confirm Roster & Start Scoring** action.
- Shows event/category details and the complete scoring criteria before judging begins.
- Makes judge autosave, two-second live refresh, scorer submission and Super Admin publication approval clear.
- Promotes Live Projection into a visible operational card with its current screen and cycle state.
- Keeps ranking, checkpoints, print, final lock and the detailed projection controls in one workspace.
- Keeps Dance Cup Automatic Scoring separate from Jack & Jill Scoring and introduces no database migration.

## Validation

- Static regression coverage added for the completed workflow.
- Existing JavaScript regression suite executed.
- PHP runtime validation is required on staging because PHP CLI is unavailable in the local workspace.

## Deployment

- Intended for staging verification first. Production is unchanged.
