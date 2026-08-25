# BDC v2.3.3-dev414

Build: 3120  
Date: 2026-08-26

## Progression and Special Category correction

- Separates current career progression from Special Competition Category on every Bachata and Salsa discipline profile.
- Keeps Novice, Intermediate and Advanced point history visible as separate labelled totals, while also showing the overall total and current progression level.
- Allows a dancer such as Melissa Jane to remain Advanced with 26 Novice points and 26 Intermediate points while independently appearing under Bachata Open.
- Updates Competitor Management filtering, counters, cards, CSV export and editing so Special Categories no longer replace progression.
- Updates duplicate merging to preserve discipline profiles, Special Categories, form submissions, useful profile data and career links.

## Verified 28-profile recovery

- Corrects dev413 before deployment: the verified 28 identities now receive 29 separate Special Category assignments without overwriting Novice, Intermediate, Advanced or All Star progression.
- Preserves MAMONG as Bachata Open and Salsa Open.
- Consolidates BDC-000549, BDC-000543 and BDC-000528 into their established identities.
- Adds a compatibility migration that repairs any already mixed Special Category values by retaining the category separately and recalculating career progression from approved points and history.

## Validation

- Verified 28-person manifest and category totals regression: passed.
- Separate progression/Special Category integration regression: passed.
- Results point labels, totals and current-level regression: passed.
- Duplicate merge preservation regression: passed.
- VERSION.json parse validation: passed.
- PHP syntax and database migration runtime: not locally runnable because PHP CLI is unavailable; required on Staging.
- Competitor Management and public Results browser workflows: not runtime-tested; required on Staging.

## Deployment

- Published to the GitHub develop release line only.
- No scoring, judge, Live dashboard, Test dashboard or projector calculation files are changed.
- Production promotion is blocked until Staging confirms Melissa-style mixed history, the exact 28-person Special Category filters, duplicate removal and export columns.
