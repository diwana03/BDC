# BDC 2.3.3-dev236

## Round-aware Live Score Matrix

- Adds a Live Score Matrix projector control for Heats, Semifinal and Final.
- Heats and Semifinal show Leaders left and Followers right with provisional place, bib, competitor, every judge mark and current score.
- Final shows confirmed couples with every judge's Relative Placement and provisional placement sum.
- Marks the Chief Judge with a star and labels all live matrices Provisional.
- Refreshes through the existing shared Test and Live display state.

## Projection round rules

- Competitors, Callbacks and Finalists remain individual and split by Leader and Follower before Final pairing.
- Confirmed Finals display couples.
- Callback and Finalist screens now use split-role pagination and non-overlapping cards.
- Projector controls are generated from the selected round type.
- Heats Full Scores appears only for a Heats round and is not shown for Semifinal or Final.
- Final-only events do not display Heats-only controls.

## Validation and deployment

- Shared Test and Live projector paths updated together.
- Screen-type allowlist and loop state updated.
- Static round-option, matrix-query and split-role checks completed.
- PHP runtime and full-screen visual validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
