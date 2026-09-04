# Release 2.3.3-dev608

## Projection roster correction

- Replaces the tiny fixed competitor portraits with row-aware sizes.
- Keeps 19 Leaders and 19 Followers readable in a balanced four-column by five-row layout.
- Keeps photo, name, prominent bib, flag and country in separate non-overlapping rows.
- Applies one consistent navy card background to competitors and judges.
- Resets inherited margins and transforms that could move identity text over portraits.
- Constrains judge portraits to their reserved frame while retaining Chief Judge styling and scoring scope.

## Parity Gate

- Testing Score Dashboard: shared `live-display/feed.php` Test data branch statically checked.
- Live Scoring Dashboard: shared `live-display/feed.php` Live data branch statically checked.
- Live Scoreboard / projector: shared roster renderer and `projector-roster-v608.css` integration checked.
- Candidate validation: JavaScript projector regression checks and repository diff checks passed.
- Staging/runtime validation: not yet tested; Production promotion remains blocked until this exact release is deployed and visually verified on Staging.

## Migration and deployment

- Database migration: none.
- GitHub `develop`: candidate prepared for publication.
- Staging: not deployed.
- Production: not deployed.
