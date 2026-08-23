# BDC v2.3.3-dev389

## Dedicated Dance Cup judge sheets

- Replaces the old Print Judge Sheet action that printed the entire administration workspace and one oversized all-judge matrix.
- Opens a dedicated print preview in a new tab, leaving the scoring workspace and scroll position untouched.
- Produces one clean A4 landscape sheet per assigned judge.
- Prints up to ten contestants per page and continues onto clearly numbered sheets without splitting rows.
- Shows contestant number, participant or team name, every saved custom criterion and its maximum, that judge’s saved mark, judge total and the category maximum.
- Includes event, date, venue, category, dance style, level, Chief Judge label, judge signature and scoring administrator/witness fields.
- Creates a blank generic judge sheet when a category has no assigned judge and ten blank rows when the roster is empty.
- Uses the same read-only print route for isolated Testing and Live data.
- Preserves the new official Solo, Couple/Duo and Team defaults and every custom criterion.
- Does not alter marks, totals, rankings, locks, submissions, judge sessions or projector data.
- No database migration. Production untouched pending Staging validation.

## Validation

- PASS: actual Dance Cup print button now routes to the dedicated document.
- PASS: Testing and Live table prefixes remain isolated.
- PASS: criteria, contestants, judges and marks are read dynamically from the selected category.
- PASS: output is read-only and contains no database mutation.
- PASS: A4 landscape sizing, per-judge pagination, repeated headers and signature fields are present.
- PASS: executable regression test covers the active button and print data path.
- NOT RUNTIME-TESTED: PHP CLI is unavailable in this workspace.
- NOT RUNTIME-TESTED: database-backed browser rendering and physical/PDF print preview require the exact commit on Staging.

## Parity Gate

- Testing Score Dashboard: candidate/static PASS through data_mode=test and bdc_test_dance_cup_* sources.
- Live Scoring Dashboard: candidate/static PASS through real bdc_dance_cup_* sources.
- Manual and Automatic judge scoring: candidate/static PASS; the print route only reads their saved criteria and marks.
- Live Scoreboard/projector: N/A — no projected data or commands changed.
- Reports/PDF: candidate/static PASS; Staging must visually verify Solo, Couple/Duo, Team and a custom 7+ criterion category in browser print preview and Save PDF.
- Production promotion remains blocked until that Staging print check passes.
