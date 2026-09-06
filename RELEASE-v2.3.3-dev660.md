# BDC v2.3.3-dev660 — projector audit repair

Build 3366

Repairs dev657-dev659 as one coherent implementation.

- Emcee matching now renders the normalized `matching_couples` screen.
- Final and Heats score matrices use separate bounded layouts instead of stacked overrides.
- Final matrix reserves all 12 finalist rows so Couple 12 cannot be clipped.
- Emcee cards retain the 5 / 5 / 2 board with safe photo, name, flag and BIB sizing.
- No scoring, pairing, judge, roster or result-data logic is changed.
