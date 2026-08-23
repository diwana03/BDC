# BDC 2.3.3-dev373 — Completed Heats Score Access

## Fixed

- Keeps a dedicated **Completed Heats Score Report** action visible after a Manual Heats round reaches `completed` in both Testing and Live.
- Opens the existing full judge-by-judge Heats report, including its **Print / Save as PDF** control, without unlocking or changing the completed round.
- Treats completed Automatic Test rounds as scoring-complete while preserving the existing Test and Live **Preview / Print Scores** actions.

## Safety

- Completed scoring forms remain locked.
- No scores, callbacks, tiers, judging calculations, automatic-scoring rules or projector behavior are changed.
- Testing and Live surfaces are released together.

## Validation

- Completed Heats print-access regression test.
- Test/Live scoring-surface parity test.
- Repository whitespace and JSON validation.
