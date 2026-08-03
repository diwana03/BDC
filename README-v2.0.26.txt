BDC v2.0.26 PATCH

PURPOSE
- Live Heats/Semifinal total calculation while entering scores.
- Manual Calculate & Sort Heats Results button.
- YES = 10, A1 = 4.5, A2 = 4.3, A3 = 4.2.
- A1/A2/A3 are case-insensitive.
- Duplicate alternates for the same judge are highlighted and blocked.
- Leaders and Followers are sorted independently, highest total first.

INSTALL
1. Extract this ZIP into the BDC portal root.
2. Open /install-v2.0.26.php once while logged in as an administrator.
3. Return to the scoring dashboard and hard-refresh the browser.

ROLLBACK
Open /rollback-v2.0.26.php.

NOTES
- The installer creates a timestamped backup of admin/scoring/index.php.
- This is an incremental patch for the current v2.0.25 baseline.
- No database migration is required.
