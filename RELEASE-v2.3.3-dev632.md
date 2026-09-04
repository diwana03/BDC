# Release v2.3.3-dev632

## Scope

- Fixes the projector countdown effect freezing on 1.
- Clears the effects canvas and overlay immediately after the five-second countdown.
- Applies to both standalone countdown and callback-reveal countdown commands.
- Leaves fireworks, celebrations, scoring, projector state and Test/Live isolation unchanged.

## Validation

- Countdown cleanup regression passed.
- Mobile callback countdown wiring passed.
- Projector control regressions passed.
- Existing effect dispatch remained unchanged.

## Deployment

- Source candidate only. No Staging or Production deployment performed by Codex.
