# Release v2.3.3-dev630

## Scope

- Fixes country-name clipping on the Call Judges One by One projector screen.
- Gives a single country the full available identity width instead of the compact multi-country width.
- Prevents country names such as Switzerland from breaking inside words.
- Preserves the existing Flag 1 through Flag 5 wrapping behavior.

## Validation

- Shared Test and Live projector stylesheet parity retained.
- Projector judge-card regression checks passed.
- Multi-country regression checks passed.
- JavaScript syntax checks passed.
- PHP rendering logic is unchanged; only the projector stylesheet cache key was advanced to dev630.

## Deployment

- Source candidate only. No Staging or Production deployment performed by Codex.
