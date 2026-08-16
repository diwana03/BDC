# BDC 2.3.3-dev239

## Round navigation and mode restoration

- Fixes the Automatic Test screen bootstrap syntax error.
- Preserves Automatic or Manual mode when opening a saved Test round.
- Keeps Automatic mode when returning through “All rounds”.
- Returns Automatic wrapper navigation to the Automatic dashboard instead of exposing the Manual dashboard.
- Keeps completed Heats and Semifinal parent rounds visible in the Live saved-round workflow after advancing.
- Existing scores, marks, callbacks, pairings and round status are preserved.

## Validation and deployment

- Test and Live navigation paths updated together.
- Static checks completed for PHP bootstrap syntax, mode-bearing links and completed-parent visibility.
- Staging workflow validation is required: Heats → Semifinal/Final → All rounds → reopen Heats.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
