# BDC v2.3.3-dev383

## One-click Emcee Random Match

- Dashboard button now starts the Emcee secure randomization immediately instead of only opening the controller.
- Opens the Emcee countdown screen in a new tab from the same click.
- Synchronizes the resulting saved pairs back to the dashboard.
- Locks Save Pairing Draft and Confirm Final Pairing while any Follower is still missing.
- Clears stale selector values when the server has no saved pairing.
- Applies to Testing and Live.
- Keeps Secure Fisher–Yates, countdown, reveal, projection and scoring logic unchanged.
- No database migration. Production untouched pending Staging validation.
