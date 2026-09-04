# BDC Portal 2.3.3-dev622

## Projector logo placement

- Keeps the BDC logo inside its original solid white rounded tile.
- Places the logo beside the complete event, division and round heading group.
- Aligns the logo down toward the round title while retaining breathing room above it.
- Keeps the event title group centered and preserves the approved projector safe area.
- Renders the header and page counters directly, removing the fragile full-page output rewrite.

## Parity Gate

- Candidate/static: shared Test and Live Jack and Jill projector renderer checked in `live-display/feed.php`.
- Candidate/static: shared projector safe-area and logo presentation checked in `public/css/projector-safe-v616.css`.
- Candidate/static: competitor, judge, live scores, provisional matrix and projector-control regression checks passed.
- Staging/runtime: not runtime-tested in this environment; Production promotion remains blocked until this exact `develop` candidate passes Staging projector checks.

## Safety

- No database migration.
- No scores, marks, rankings, judges, competitors, links, tokens or event data are changed.
- Production is not deployed or modified by this release.
