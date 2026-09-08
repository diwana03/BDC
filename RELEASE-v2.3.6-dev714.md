# BDC v2.3.6-dev714

## Adaptive contestant portraits with protected safe margins

- Sizes All Contestants pages according to whether they contain one row or two rows.
- Uses larger portraits on a single row and a height-safe portrait size on two rows so the second row remains fully visible.
- Adds a contained 16 percent audience close-up inside each circular portrait without increasing the card footprint.
- Preserves the 10 percent top and bottom and 5 percent left and right projector safe area.
- Keeps the saved WDC adjusted-photo source and legacy roster recovery from dev713.
- Does not change marks, scores, placements, judging, BDC identities, or SDC identities.

## Validation

- Adaptive row sizing, close-up framing, WDC identity, legacy roster, projector recovery, pagination, parity, and universal safe-layout static tests: passed locally.
- PHP syntax: not locally runtime-tested because PHP CLI is unavailable in this workspace.
- Deployment: `develop` Test candidate only.
- Production: untouched and blocked pending separate runtime approval.
