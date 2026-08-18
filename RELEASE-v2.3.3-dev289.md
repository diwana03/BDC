# BDC 2.3.3-dev289 — Build 2995

## Final scoring integrity

- Final judge progress now reflects actual saved placements instead of treating a submitted session as automatically complete.
- A submitted Final session with missing, duplicate or incomplete Top-N placements is safely reopened while surviving marks remain preserved.
- Test and Live use the same repair and progress rules.
- Final pairing drafts cannot be replaced after judging begins; the authorised REMATCH flow remains the recovery path.

## Projection identity

- Bib numbers and country flags are now included by default in shared contestant and couple scoring/status projections.
- Final matrix couples show the pair number plus both leader and follower bib/flag identity.
- Heats, Semifinal and Final projection data remain shared across Test and Live.

## Destructive-action protection

- Cancel Final is collapsed behind a lock control.
- The operator must type `CANCEL FINAL` and accept a confirmation before the child Final and its pairing data can be removed.

## Validation

- Repository whitespace and patch validation passed.
- PHP CLI is unavailable in the development workspace; Release Manager staging deployment must perform the PHP runtime validation.
