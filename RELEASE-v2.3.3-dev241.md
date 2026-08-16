# BDC 2.3.3-dev241

## Multi-select callback tie resolution

- Replaces the incorrect single-winner tie action with exact-quantity Chief Judge selection.
- Calculates remaining callbacks independently for Leaders and Followers.
- Example: 6 confirmed of a Tier-2 quota of 10 requires exactly 4 selections from the tied group.
- Disables submission until the correct number of callbacks is selected.
- Enforces the same quantity again on the server.
- Allows the Chief Judge to assign remaining tied competitors uniquely to available A1, A2 and A3 positions.
- Eliminates tied competitors remaining after callback and alternate positions are filled.
- Keeps the next-round controls locked until all tie groups are resolved.
- Uses one shared resolver and one shared panel across Test Manual, Test Automatic, Live Manual and Live Automatic.

## Validation and deployment

- Added regression guards for exact selection count and alternate preservation.
- Existing scores and marks remain unchanged until a tie decision is submitted.
- Staging validation required with the 6-confirmed + 7-tied → select-4 scenario.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
