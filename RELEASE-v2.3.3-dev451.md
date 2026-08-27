# BDC 2.3.3-dev451 · build 3157

## Consolidate Dance Cup Automatic projection

- Removes the duplicate Live Projection shortcut from page navigation.
- Removes the duplicate Projection Controls shortcut from Scoring Rules.
- Removes the repeated early Projection card.
- Keeps one Step 6 Live Projection section with current screen, auto cycle status, Projection Control, Projector Screen and link regeneration.
- Preserves all projection endpoints, polling, state and Test/Live data isolation.
- Introduces no database or scoring calculation change.

## Parity Gate

- Testing Score Dashboard: shared Automatic Dance Cup views render with `data_mode=test` and retain isolated projector URLs.
- Live Scoring Dashboard: the same shared views render the real-data workflow with one Projection section.
- Projector: projection controller and audience projector endpoints are unchanged.

## Validation

- Focused projection consolidation regression passed.
- Complete JavaScript regression suite passed.
- PHP/browser runtime remains required on Staging.
- Production promotion is blocked until this exact release passes Staging runtime verification.
