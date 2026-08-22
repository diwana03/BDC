# BDC v2.3.3-dev339

## Premium aligned judge headers

- Rebuilds judge headers into a compact two-column layout with the official BDC logo on its white tile and all event information in one readable content column.
- Prevents the category, round and judge name from collapsing into a narrow right-side strip on mobile.
- Presents category, round, judge identity, Test status and Chief Judge status as compact wrapping chips beneath the event title.
- Uses responsive logo and title sizing at 620 px and 390 px breakpoints while keeping Light as the default theme.
- Applies the same branding transformation to criteria, Heats, Semifinal and Final judge pages.

## Parity Gate

- Testing Score Dashboard: Test criteria, Heats/Semifinal and Final judge headers use the shared premium structure.
- Live Scoring Dashboard: Live criteria and the shared Heats/Semifinal/Final judge surface use the same structure.
- Projector: unchanged because this release only affects judge-facing headers.

## Validation

- JavaScript syntax checks passed for the branding and theme loaders.
- Static header markers, Test/Live cache parity, mobile breakpoints and logo constraints passed.
- `git diff --check` and `VERSION.json` parsing passed.
- PHP CLI is unavailable in this workspace; browser/runtime validation remains required on Staging.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys the exact commit to Staging.
- Production: unchanged and blocked until Staging runtime approval.
