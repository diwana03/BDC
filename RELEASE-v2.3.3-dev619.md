# BDC 2.3.3-dev619

Build 3325

## Projector correction

- Removes the Full Screen button from the audience-facing Jack and Jill projector.
- Removes the Full Screen button from the audience-facing Dance Cup projector.
- Keeps Full Screen available only inside the corresponding administrator projection-control panels.
- Refreshes the shared projector safe-area stylesheet with a new cache key so the BDC logo and official badge visibly respect the 5 percent left/right safe area.
- Keeps the existing 10 percent top/bottom audience-safe area, balanced competitor pagination, scoring, judge links, tokens and stored data unchanged.

## Database

No migration.

## Verification

Static projector regression checks pass. Staging runtime verification remains required before Production.
