# BDC 2.3.3-dev226

## Projector competitor card repair

- Restored the missing `.item` wrapper around individual competitor cards.
- Keeps each fallback photo, competitor name, bib number, flag and country inside the same projector grid cell.
- The repair is in the shared `live-display/feed.php`, so Test and Live use identical rendering.
- Added the card-wrapper structure to the automated scoring parity gate.

## Parity Gate

- Test projector: shared `live-display/feed.php` with `data_mode=test`.
- Live projector: shared `live-display/feed.php` with `data_mode=real`.
- Projector control: unchanged shared `admin/live-screen/control.php`.
- Static validation: card wrapper, shared data-mode feed, cute-animal fallback and per-contestant country display checked together.
- Staging/runtime validation: required after deploying this exact candidate.

## Migration and deployment

- No database migration.
- Push target: `develop` only.
- Production is not modified.
