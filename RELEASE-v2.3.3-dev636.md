# BDC v2.3.3-dev636 — Projector roster regression recovery

## Changes

- Removes the dev635 outer-projector DOM rewrite that compressed judge portraits and text inside otherwise full-size cards.
- Restores the native shared competitor and judge card renderers used by both Test and Live projection.
- Preserves each competitor's valid primary country value so Round 1, Round 2 and later Flight Call cards can resolve their flag again.
- Uses the same three-column, five-row capacity for all non-Final roster surfaces: at most 15 Leaders and 15 Followers per page, followed by the real remainder.
- Keeps complete competitor country text below its flag and retains the asset-ready iframe crossfade so polling does not flash the display.

## Validation

- Candidate/static: all 24 projector JavaScript regression tests, focused native-card and silent-refresh checks, JSON parsing and repository whitespace checks. The broader repository suite still contains an unrelated legacy Dance Cup flag assertion, and PHP CLI is unavailable in this workspace.
- Staging/runtime: Not Runtime-Tested. Production promotion remains blocked until this exact commit is deployed and visually checked on the Test and Live projector screens with Round 1, Round 2, Judges and page transitions.

## Parity Gate

- Testing Score Dashboard: shared Test projector session and roster paths checked statically.
- Live Scoring Dashboard: shared Live projector session and roster paths checked statically.
- Live Scoreboard/projector: `live-display/index.php`, `live-display/feed.php` and `public/css/projector-roster-v615.css` checked statically for native roster rendering, flags, 15-per-role paging and silent refresh.

## Migration

- No database migration.
