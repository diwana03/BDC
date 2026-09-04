# BDC 2.3.3-dev617

## Readable competitor projection pages

- Limits the shared Test and Live competitor, callback and finalist projector screens to a maximum of 15 Leaders and 15 Followers per slide.
- Uses three card columns per role, allowing up to five rows without shrinking portraits and names into an unreadable dense grid.
- Preserves balanced pagination: 26 Leaders become 13 + 13, while 27 Followers become 14 + 13 across two slides.
- Keeps the approved BIB-left, photo-centre, name-and-country-right card design and the dev616 10% safe area.
- Does not alter Flights, judges, scoring, judge links, tokens, database values or the separate Dance Cup module.

## Validation

- Candidate/static: focused 15-per-role pagination regression, existing balanced-pagination, competitor-card, renderer-recovery, hot-path and stacked-card checks, JSON parsing and `git diff --check`.
- Migration: none.
- Deployment: source candidate only; the user deploys through Release Manager.
- Runtime: not tested on Staging. Production promotion remains blocked until the exact candidate is deployed and the Test/Live projector page transitions are checked.

## Parity gate

- Test projector: shared state and feed paths checked statically.
- Live projector: identical shared state and feed paths checked statically.
- Dance Cup projector: unchanged.
- Staging/runtime: pending.
