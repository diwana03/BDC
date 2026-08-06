# BDC 2.3.3-dev21

## Historical partner resolution

- Fills a missing Participant Results partner from the corresponding recorded FINAL placement.
- Requires the same event, division and normalized final placement with the opposite dance role.
- Supports equivalent placement labels such as `1`, `1st` and `First`.
- Resolves a partner only when exactly one matching finalist exists.
- Leaves the partner blank when the FINAL match is missing or ambiguous.
- Never infers a partner from equal points.

This release changes partner display only. It does not recalculate or rewrite points, placements, participant history, repository documents or database records.
