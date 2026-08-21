# BDC v2.3.3-dev328

## Directory search, compact roster layout and natural score display

- Starts Dance Cup competitor and judge suggestions after the first typed character.
- Returns up to 100 ordered matches so typing `a`, `b` or another initial exposes the matching database records in the scrollable suggestion list.
- Keeps the Bib field compact on the left while giving the competitor-name search the larger right-hand area on desktop and mobile.
- Displays Automatic report marks naturally: `10`, `4.3`, `4.2` instead of `10.00`, `4.30`, `4.20`.
- Changes presentation only; stored weights, score precision, averages, rankings and callback logic are untouched.

## Validation

- Candidate/static: directory JavaScript syntax, one-character client/server thresholds, search limits, Test/Live report parity, version JSON and whitespace checks passed.
- Candidate/static: numeric formatting strips only display trailing zeroes and does not alter database values or calculation services.
- Database migration: not required.
- Staging/runtime: pending user deployment and verification against the connected competitor and judge databases.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Dance Cup Test and Live category workspace: shared competitor/judge layout and directory client checked.
- Competitor Database and Judge Database endpoints: one-character matching and ordered results checked.
- Test and Live Automatic result sheets: identical natural mark display checked.
- Manual scoring, calculation, submission, checkpoints and projection: logic unchanged.
