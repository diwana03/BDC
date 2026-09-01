# BDC 2.3.3-dev549 — Competitor Merge SQL Fix

Build 3255 · 2026-09-01

## Fix

- Fixes `SQLSTATE[23000]` / MySQL error 1052 when merging competitors with discipline profiles.
- Explicitly qualifies the source and destination `dance_role`, `current_division`, and `special_category` columns.
- Preserves the transaction and existing scoring-history safeguards; a failed earlier merge was rolled back and did not delete the duplicate profile.

## Gate

- Full JavaScript/static regression suite required before push.
- Production remains blocked until this exact commit passes the competitor merge scenario on Staging.
