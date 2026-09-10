# BDC 2.3.6-dev729

## Change

- Keeps paginated judge boards on one stable four-by-two card grid, so Page 2 cannot enlarge portraits or overlap names.
- Centres incomplete six- and seven-judge final rows while preserving the exact card and 4:5 photo size used on Page 1.
- Enlarges judge portraits within a reserved photo row and keeps the name and country in their own non-overlapping rows.
- Uses the available width on sparse Jack & Jill Leader and Follower pages so first names are not unnecessarily truncated.
- Prevents a single remaining competitor from stretching across the entire role panel.

## Validation

- Static regression checks cover paginated grid stability, centred incomplete rows, fixed 4:5 portrait geometry, separate text rows and sparse competitor-name layout.
- Existing projector and full JavaScript regression suites were run locally.
- Deployment: local source candidate only. Production is unchanged and no push was performed.
