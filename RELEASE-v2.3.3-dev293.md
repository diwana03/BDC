# BDC 2.3.3-dev293 · Build 2999

## Compact scoring recovery

- Adds **Refresh Status** and **Backups** beside **Open Judge Links** in Automatic Final scoring.
- Replaces the large always-visible backup section with a compact collapsed **Backups & Score History** drawer.
- Automatically names manual checkpoints with the event and current time when no note is entered.
- Creates a score checkpoint immediately after each Live or Test judge submits.
- Names judge checkpoints in the format: `Event · After J1 submission · 09:42:18`.
- Keeps the latest 25 automatic checkpoints per round.

## Human-readable history

- Converts raw audit JSON into operational descriptions.
- Shows clear labels for judges, reasons, pair counts, ranking depth, promoted finalists and algorithm versions.
- Keeps the original audit payload unchanged in the database.
