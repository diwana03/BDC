# BDC v2.3.3-dev637 — Mobile projector and Flight Round integrity

## Changes

- Adds a dedicated 9:16 portrait layout for the one-by-one judge call so assignment, portrait, name, scope, flags and complete country names remain visible without horizontal overflow.
- Leaves the approved 10 m × 5.5 m, 16:9, 4K main-display layout unchanged, including its safe area and landscape card sizing.
- Removes Full Screen and `Press F11` controls from both audience projector pages; Full Screen remains on the Jack and Jill and Dance Cup control panels.
- Separates Flight Round selection from roster pagination so selecting Round 2 cannot offset or hide the contestants assigned to Round 2.
- Advances the active shared projector stylesheet cache key to force the corrected portrait layout to load.

## Validation

- Candidate/static: projector regression tests, focused 9:16 layout and Flight Round integrity checks, inline JavaScript parsing, CSS brace validation, JSON parsing and repository whitespace checks.
- Staging/runtime: Not Runtime-Tested. The exact commit must be verified on the shared Test and Live projector using the 16:9 4K main display and a 9:16 mobile viewport before Production promotion.

## Parity Gate

- Testing Score Dashboard: shared Test projector paths checked statically.
- Live Scoring Dashboard: shared Live projector paths checked statically.
- Live Scoreboard/projector: audience fullscreen removal, portrait judge call, silent refresh and Flight Round contestant selection checked statically.

## Migration

- No database migration.
