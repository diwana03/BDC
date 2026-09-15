# BDC 2.3.6-dev745 — Standard J&J Detailed Archive Refresh

## Problem fixed

Previously published standard Salsa and Bachata Jack & Jill repository links remained frozen as their original basic HTML snapshots. The detailed Heats and Final report improvements did not change those existing files, and the normal publication screen had no refresh control.

## Safe correction

- Adds **Refresh Detailed Result Archives** to an already-published standard J&J competition for Super Admin.
- Rebuilds the existing Heats, Final and Points HTML files from the current reviewed reports.
- Keeps every existing public repository URL unchanged.
- Includes **Readable Pages** and **Landscape, All Judges** inside refreshed Heats and Final archives.
- Uses the actual active Final roster for Heats advancement, including approved manual promotions.
- Creates protected copies of all three current files before replacement.
- Audits old and new checksums for all refreshed archives.
- Restores every old file if any replacement or audit step fails.

## Data boundary

This action changes stored HTML report files only. It does not recalculate or modify:

- judge marks;
- Heats totals or ranks;
- Final roster, matching, placements or Relative Placement;
- BDC or SDC points;
- participant result records; or
- projection data.

## Test and Live parity

- Live standard publication screen: updated.
- Isolated Test standard publication screen: updated identically against Test result documents.
- Special category publication refresh remains available from dev744.

## Parity Gate

- **Testing Score Dashboard:** candidate/static validation passed for the isolated standard publisher, detailed Heats source, detailed Final source, Points archive, protected replacement and rollback path.
- **Live Scoring Dashboard:** candidate/static validation passed for the matching standard publisher and real result-document table; the refresh changes archived HTML only.
- **Live Scoreboard / projector:** inspected and unchanged. This release does not alter projector commands, projected competitors, judges, scores, callbacks, finalists or reveal state.
- **Staging/runtime:** not yet tested. Production promotion is blocked until this exact candidate is deployed to Staging and the browser refresh flow is verified against a published standard J&J competition.

## Validation

- Added `tests/standard-result-archive-refresh-v745.js`.
- Focused detailed Heats, detailed Final and archive regression checks passed locally.
- Full static regression suite and repository sanity checks passed for all locally executable checks.
- Two PHP-dependent checks were unavailable because this workspace has no PHP executable; PHP runtime verification remains required on Staging/Test before Production promotion.

## Deployment boundary

Push `dev745` to `develop` for Staging/Test first. Production remains untouched until the refresh is verified on Staging with a published standard J&J competition.
