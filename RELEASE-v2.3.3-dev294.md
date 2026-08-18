# BDC 2.3.3-dev294 · Build 3000

## Central scoring recovery

- Adds **Scoring Backups** directly under **Live Operations** in the main admin menu.
- Adds the same shortcut to dashboard Quick Actions.
- Provides one central event-and-round selector for checkpoints, transactions and restores.
- Supports Live history by default and an isolated Test history for Super Admin.
- Allows Admin, Scorer, Master Scorer and Super Admin to create and restore scoring checkpoints.

## Restore correction

- Every checkpoint now has a clearly visible red **Restore** action.
- Previously restored checkpoints can be restored again when required.
- Restore requires a reason, exact `RESTORE SCORES` confirmation and a final warning.
- A safety checkpoint of the current state is always created before recovery.
- Recovery includes marks, results, Final pairs, Final placements and judge submitted/draft lock states.
