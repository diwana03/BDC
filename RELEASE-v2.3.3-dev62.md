# BDC v2.3.3-dev62

## Automatic Scoring UI + Live Panel Fix

- Moves the optional Registration Desk card to the top of Automatic Heats/Semifinal setup.
- Removes the redundant explanatory sentence above Judge Live Scoring.
- Hardens the Judge Live Scoring control so missing judge-session storage no longer returns an HTTP 500.
- Judge-session storage is verified/created before live progress is queried.
- Before judges and competitors are ready, the live panel shows a normal waiting state instead of a server error.
- Manual scoring, tier calculations, special-category points, BDC IDs and Release Manager workflow are unchanged.
