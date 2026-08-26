# BDC v2.3.3-dev426

Build: 3132  
Date: 2026-08-26

## Dance Cup roster management

- Adds contestant move, remove and roster-order controls to Automatic and Manual Dance Cup.
- Adds judge move, remove and post-add Chief Judge reassignment to both workflows.
- Enforces exactly one Chief Judge before Automatic Judge Links can open.
- Blocks contestant or judge removal after their scoring marks exist and keeps submitted competitions locked.
- Stacks the Automatic contestant and judge panels at laptop widths so controls no longer overflow off-screen.

## Draft recovery

- Adds Delete Draft to Manual and Automatic category cards.
- Requires typed `DELETE` confirmation and rejects non-Draft categories.
- Removes only the selected category's isolated roster, links, marks, results, criteria and checkpoints.
- Removes the parent event only when it is an empty Draft after category deletion.

## Validation

- Shared Manual/Automatic roster action regression: passed.
- Chief Judge uniqueness and Judge Links gate regression: passed.
- Scoring and submitted-state protection regression: passed.
- Responsive laptop stacking regression: passed.
- Protected Draft deletion regression: passed.
- All 59 locally executable Node regressions: passed.
- PHP runtime validation remains required on the isolated Staging deployment.
