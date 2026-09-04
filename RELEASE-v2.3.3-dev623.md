# BDC Portal 2.3.3-dev623

## Mobile projection workflow

- Shows each saved Flight Round directly on the Mobile Projection Remote and sends the exact selected round to the live display.
- Starts the existing five-second projector countdown automatically when Callback Reveal is selected.
- Adds a separate `5 · 4 · 3 · 2 · 1 Countdown` effect to both mobile and desktop projection controls.
- Adds `Call Judges One by One` without changing the normal full Judges screen.
- Uses Previous and Next to call individual judges manually, or the existing Auto Page controls to advance through assigned judges.

## Parity Gate

- Candidate/static: shared Test and Live mobile commands checked in `projection-remote/index.php` and `app/Services/MobileProjectionRemoteService.php`.
- Candidate/static: shared desktop projection control checked in `admin/live-screen/control.php` and `admin/live-screen/live-action.php`.
- Candidate/static: shared audience state, paging and rendering checked in `live-display/index.php`, `live-display/state.php`, `live-display/advance.php` and `live-display/feed.php`.
- Staging/runtime: not runtime-tested in this environment; Production promotion remains blocked until this exact `develop` candidate passes Staging projector checks.

## Safety

- No database migration.
- Existing Judges, scoring, callback data, flight assignments, links and projector tokens are preserved.
- Callback countdown and judge calling use the existing projector session and overlay channels.
- Production is not deployed or modified by this release.
