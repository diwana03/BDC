# BDC 2.3.3-dev164

Emergency correction for the projector Final Relative Placement view.

## Fix

- Corrects the HTTP 500 caused by reading competitor names directly from the final-pairs table.
- Couple names now load through `leader_entry_id` and `follower_entry_id`, matching the established Final Result/PDF query.
- The landscape matrix continues to show final rank and every judge's Relative Placement.
- Applies to Testing and Live.

## Deployment

- Branch: `develop`
- No historical migration was changed.
- No Production configuration was changed.
