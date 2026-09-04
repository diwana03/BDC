# BDC v2.3.3-dev627

## Projector judge calling

- Adds a large assigned-judge dropdown directly above the projector-screen controls on desktop and mobile.
- Calls any selected assigned judge immediately instead of forcing the operator to start with Judge 1.
- Keeps Previous, Next and optional Auto Page navigation for fast sequential calling.
- Shows a clear disabled state when a round has no assigned judges.
- Forces readable high-contrast text for judge, event, division and round dropdown options across mobile and desktop controls.
- Enlarges and centres the selected judge card, photo, name, assignment, scope and country inside the existing 5% horizontal and 10% vertical projector-safe area.
- Places each judge country below its flag and wraps long country names across two lines in Round 1, Round 2 and later rounds.
- Leaves the multi-judge roster layout unchanged.

## Parity and safety

- Uses the same Test/Live assignment tables and the same Chief-first judge order as the audience projector.
- Gives Live Judge Browser Scoring and the Test automatic judge panel the same premium navy, burgundy, champagne and status-aware presentation.
- Keeps Reopen Scoring prominent for every submitted and locked judge while leaving not-started and actively-scoring judges protected from an invalid reopen action.
- Changes no judge assignments, scores, results, tokens or event data.
- Database migration: none.
- Runtime Staging verification is required before Production promotion.
