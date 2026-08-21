# BDC 2.3.3-dev314 — Scoring roster checkpoints

- Blocks duplicate competitors in the same round and reports the existing role and bib.
- Removes submitted competitors from search suggestions.
- Adds **Save Competitors** and **Submit Competitors** checkpoints to Test and Live Automatic scoring.
- Locks competitor additions, removals and bib changes after submission; Super Admin may reopen with a required reason.
- Keeps judge links disabled until the competitor roster is submitted.
- Adds **Refresh Status**, **Backups** and **Open Judge Links** to Automatic Heats and Semifinals.
- Adds the audited emergency **UNLOCK ALL** control to Automatic Heats and Semifinals without deleting existing scores.
- Includes the database migration for persisted Test and Live roster checkpoint state.

Deployment: deploy the exact `develop` commit for this release to Staging first. Do not overwrite `config.php`.
