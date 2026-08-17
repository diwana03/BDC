# BDC v2.3.3-dev253 — Timed Random Match Reveal

- Keeps generated Final pairings hidden for a 30-second presentation countdown.
- Runs the drum-roll overlay and sound during the delay, then reveals the matches automatically.
- Returns the projector to its holding screen during the countdown so an earlier matching view cannot expose the new draw.
- Enlarges bib numbers above names in the visual hierarchy.
- Rebalances projected Final couple cards to use more screen width and height with a small bottom margin.
- Preserves the existing country-flag size.
- Applies identically to Test and Live because both use the shared projection feed.

No database migration or configuration change is required.
