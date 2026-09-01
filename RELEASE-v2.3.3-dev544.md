# BDC v2.3.3-dev544

## Fix

- Forces the complete Dance Cup Automatic scoring workspace to render after the roster on the deployed category page.
- Adds a stable scoring-shell integration point around the shared Judge Scoring, matrix, ranking, approval and projection workspace.
- Retains the `Confirm Roster & Start Scoring` redirect to the real Judge Scoring section.
- Uses the same page and shared workspace for isolated Test and Live data.

## Validation

- Production baseline reproduced on `v2.3.3-dev543`: roster confirmation redirects correctly, but the deployed DOM contains neither `automatic-scoring-shell`, `automatic-workspace` nor `judge-progress`.
- Focused Automatic Dance Cup workflow regression: passed.
- `VERSION.json` parse and diff whitespace checks: passed.
- Full candidate suite: 151 JavaScript checks with the same 28 failures as the unchanged repository baseline.
- Database migration: none.

## Parity Gate

- Testing Score Dashboard: shared page, workspace include and Test data-mode routing included in the candidate.
- Live Scoring Dashboard: shared page and workspace include included in the candidate.
- Projector: shared workspace links are restored; projector implementation and state are unchanged.
- Staging runtime: pending deployment of this exact candidate.
- Production promotion: blocked until the deployed DOM and roster transition are verified.

## Deployment Status

- Candidate only. Not deployed to Staging or Production.
