# BDC 2.3.3-dev15

## Staging Release Manager

- Adds a manual **Sync Results Repository** action.
- Copies published result files one way from Production to Staging.
- Creates a timestamped backup of the existing Staging repository first.
- Verifies the copied file count and restores Staging automatically if the sync fails.
- Uses fixed protected paths and never writes to the Production repository.

## Scope

- No database schema, points, scoring, leaderboard, publishing, or Production deployment logic changes.
