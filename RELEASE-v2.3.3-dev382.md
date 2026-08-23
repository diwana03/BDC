# BDC v2.3.3-dev382

## Emcee and dashboard Random Match synchronization

- Uses the active Emcee link as the dashboard Random Match control, preventing two separate randomizers.
- Live-syncs saved Emcee pairings into every Leader/Follower selector and status cell without refreshing the scoring dashboard.
- Refreshes an open Emcee controller when an authorized dashboard-side fallback randomization changes the saved pairs.
- Prevents a stale blank dashboard from overwriting an Emcee match with “Partner pending.”
- Applies identically to Testing and Live.
- Keeps Secure Fisher–Yates, countdown, reveal, pairing confirmation and scoring gates unchanged.
- No database migration. Production untouched pending Staging validation.
