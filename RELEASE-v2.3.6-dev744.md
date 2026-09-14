# BDC 2.3.6-dev744 — Detailed Heats and Actual Final Roster

## Outcome

- Replaces the reduced special-category Heats archive with the complete reviewed Heats report.
- Preserves all judge marks, totals, judge ordering, Chief Judge, judge key, witnesses, readable pages, print output and the all-judge landscape view.
- Makes the actual active child-round roster authoritative for published advancement.
- Shows approved manual additions as `FINALIST · PROMOTED` or `SEMIFINALIST · PROMOTED`.
- Shows an original automatic callback removed from the active child roster as `NOT ADVANCED`.
- Adds the public council division label, such as `BDC INTERMEDIATE`, `BDC OPEN` or `SDC INTERMEDIATE`, to Heats report headings.
- Widens the Advancement column so its labels are not clipped.

## Protected archive refresh

For an already-published special-category result, Super Admin now uses **Refresh Heats & Final Archives**. The operation:

1. captures both the readable and landscape layouts for Heats and Final;
2. creates protected copies of both existing repository files;
3. replaces both archives together; and
4. restores the previous files if either replacement fails.

The refresh does not recalculate or change scores, rankings, placements, points, BDC/SDC IDs or projection data.

## Test and Live parity

- Live Heats report: updated.
- Isolated Test Heats report: updated with the same advancement rules and presentation.
- Standard Test and Live publishers: continue to snapshot the detailed Heats endpoint.
- Special Live publication: now snapshots both detailed Heats and Final reports.
- Projector: unaffected.

## Validation

- Added `tests/detailed-heats-actual-finalists-v744.js`.
- Static regression suite and repository sanity checks must pass before deployment.
- PHP runtime verification is required on Staging/Test before any Production promotion.

## Deployment boundary

Deploy `dev744` to `develop` for Staging/Test first. Production must remain untouched until Staging confirms the published Heats link shows the complete report and the manually approved Final roster.
