# 2.3.3-dev457

- Rebuilds Dance Cup judge sliders as premium score controls for desktop and mobile.
- Gives every slider a larger handle, colored progress track, large score readout and clear awaiting/selected state.
- Adds an explicit Set intentional 0 action so zero cannot be confused with an untouched criterion.
- Shows criteria completed and live total on every contestant card.
- Highlights incomplete contestant cards and provides a sticky Next missing score action.
- Keeps Submit Scores disabled until every criterion is explicitly scored.
- On an incomplete submission attempt, scrolls to and highlights the first missing criterion.
- Preserves automatic draft saving, Test/Live parity and all scoring calculations.
- Does not change Jack and Jill or the database schema.
