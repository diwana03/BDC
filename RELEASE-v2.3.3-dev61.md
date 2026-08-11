# BDC v2.3.3-dev61

## Automatic Heats Controller Fix

- Removes the fragile Automatic output-buffer rendering path that was causing HTTP 500 errors.
- Automatic Heats and Semifinals now open through a dedicated stable controller.
- The Automatic controller uses the same BDC scoring tables and the same setup concepts as Manual: category/tier settings, judge setup, Leader/Follower entry, bib management and optional Registration Desk.
- Manual score entry is replaced by Judge Live Scoring with secure judge browser links.
- Automatic Finals continue through the existing Relative Placement Final workflow.
- Special-category fixed points, BDC IDs, eligibility handling and Release Manager workflow are unchanged.
