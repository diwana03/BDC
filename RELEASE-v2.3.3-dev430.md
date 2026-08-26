# BDC v2.3.3-dev430

Build: 3136  
Date: 2026-08-26

## Unified Dance Cup scoring workspace

- Keeps the complete Automatic workflow on the Automatic Setup page instead of sending scorers to a separate Judge Links screen.
- Presents one ordered workflow: Roster, Judge Scoring, Checkpoint and Print, Ranking, Submit and Projection.
- Adds judge links, live submission progress, safe link regeneration and judge reopening directly below the roster.
- Adds a live judge-total matrix that refreshes as marks arrive, plus calculated rankings on the same page.
- Adds named full-state checkpoints, printable judge sheets, Calculate and Sort Ranking, Submit Scores and Lock, and projector controls to the same workspace.
- Gives Manual the same ordered workflow navigation while keeping its roster, score sheet, checkpoint, print, calculation, ranking, submission and projection controls together.
- Removes the incorrect Manual-to-Automatic shortcut and retains strict Manual/Automatic and Test/Live isolation.

## Validation

- Unified Automatic workspace regression: passed.
- Unified Manual workspace regression: passed.
- Live judge progress and score-matrix regression: passed.
- Existing JavaScript regression suite: passed.
