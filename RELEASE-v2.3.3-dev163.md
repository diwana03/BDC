# BDC 2.3.3-dev163

Projector loop-delay corrective release.

## Fix

- Changing **Tab Delay** while the projector loop is running now saves immediately and restarts the timing interval with the selected value.
- Changing the delay while stopped keeps the selection ready for the next **Start Loop** action.
- The control displays a clear confirmation or error after a delay change.
- Testing and Live use the same corrected controller.

## Deployment

- Branch: `develop`
- No historical migration was changed.
- No Production configuration was changed.
