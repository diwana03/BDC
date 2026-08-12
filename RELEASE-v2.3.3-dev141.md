# BDC v2.3.3-dev141

## Judge review workflow

- Adds a yellow `LATER` option to the Live Heats judge phone screen.
- Shows all LATER competitors in a Review Later list with direct navigation.
- Prevents submission until every LATER decision is resolved.
- Replaces the visible Blank action with `NO`; NO remains zero points.
- Preserves the existing scoring weights: YES 10, A1 4.5, A2 4.3, A3 4.2.

## Secure URL regeneration repair

- Regenerating a Live or Test judge URL now changes only the secure token.
- Existing judge status, marks, progress, timestamps and submission locks are preserved.
- Reopening submitted scoring remains a separate audited administrator action.
