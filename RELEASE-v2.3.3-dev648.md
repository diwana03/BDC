# BDC v2.3.3-dev648 — Judge search and projector runtime fixes

Build 3354

## Changes

- Keeps active Judge Database profiles searchable and selectable even when `country` and `country_code` are blank or NULL.
- Normalizes optional country metadata in the shared judge search response so a missing country produces an empty flag instead of breaking or dropping the autocomplete result.
- Preserves canonical Judge Database ID, full/display name, Judge ID and existing name / Judge ID / Instagram matching. Country remains optional metadata, never a requirement for a valid judge result.
- Keeps Test and Live Automatic Scoring on the same shared Judge Database selection path.
- Preserves the approved existing 4 + 4 + 3 judge projector card architecture, photos, Chief Judge styling, scoring scope and Test/Live scoring logic.
- Fixes the active projector runtime instead of clipping country names with CSS: judge flag paths now drive compact display codes such as DOM, SWE, COL, ESP, SUI, KOR, AUS and RUS after every feed load.
- Keeps 1–5 judge flags on one row with responsive spacing and a fixed vertical gap below the judging scope so multi-country identities cannot collide.
- Forces the outer Live Display wrapper to reload the current roster and safe-area styles instead of retaining stale projector CSS.
- Adds the approved two-line breathing space between the shared logo/event heading and the presentation body across shared projector presentations without redesigning the existing card/grid renderer.

## Validation

- Added `tests/projector-judge-country-runtime-v648.js` to guard the active wrapper cache refresh, real flag-derived display-code mapping, one-row country strip, and shared heading gap.
- Existing Judge Database and projector regression suites remain part of the PHP Runtime Gate.
- No database migration is required.

## Parity Gate

- Testing Score Dashboard: shared canonical Judge Database path remains unchanged.
- Live Scoring Dashboard: shared canonical Judge Database path remains unchanged.
- Shared Test/Live projector: same outer wrapper and presentation styles are used in both modes.

## Deployment status

- Candidate source: `develop`
- Staging runtime: NOT YET RUNTIME-TESTED. Deploy the exact dev648 `develop` commit through BDC Release Manager and verify the Judges screen plus one-by-one Judge Call.
- Production: BLOCKED until that exact Staging commit passes runtime verification and is explicitly promoted through Release Manager.
