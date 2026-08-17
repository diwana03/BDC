# BDC 2.3.3-dev246

## Round continuity and locking

- Keeps completed Heats and Semifinal rounds visible instead of removing them when a child round is created.
- Opens the All Rounds link in the round's actual Manual or Automatic area.
- Copies `scoring_mode` from Heats to Semifinal and Final in both Test and Live workflows.
- Includes scoring mode when locating an existing child round, preventing Manual and Automatic workflows from being mixed.
- Locks completed rounds against score, judge, entry and callback changes while preserving all visible results.
- Allows Scorer, Master Scorer and Super Admin roles to unlock a completed round by confirming `RESUBMIT`.
- Preserves existing child rounds so corrected callbacks can be synchronised after resubmission.
- Includes the dev245 public zero-point result cleanup.

## Scope

- Test and Live scoring workflows updated together.
- No database migration.
- No Production deployment.
