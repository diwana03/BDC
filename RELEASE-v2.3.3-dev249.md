# BDC v2.3.3-dev249 (Build 2955)

## Restored next-ranked Final promotion

- Restores **Promote Next Ranked Leader** and **Promote Next Ranked Follower** on callback-derived Final dashboards.
- Applies identically to Test and Live data and to Manual and Automatic workflows.
- Keeps the option available whenever another ranked competitor exists, including when Leader and Follower counts are already balanced.
- Shows the exact next competitor, source rank, bib, current role count, and resulting role count before confirmation.
- Selects strictly from the preceding round's ranked results and prevents the same competitor from being added again after removal.
- Locks promotion after Final marks or Final results exist.
- Resets an existing unscored pairing when the finalist list changes, then requires pairing again.
- Records the promotion, source status/rank, before/after counts, and pairing reset in the scoring audit trail.

## Deployment

- Database migration: none.
- Configuration change: none.
- Release target: `develop` only. Staging and Production are not deployed by this release.
