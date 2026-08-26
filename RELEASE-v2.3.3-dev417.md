# BDC v2.3.3-dev417

Build: 3123  
Date: 2026-08-26

## Competitor Management dashboard

- Adds All Participants as the first summary card.
- Shows the complete number of competitor identities in the database.
- Clicking the card clears search, status, country, dance, role, division and missing-information filters and returns to page one of the full list.
- Highlights All Participants when the list is unfiltered.
- Uses a responsive five-card grid without squeezing the cards on smaller screens.

## Validation

- All Participants count query regression: passed.
- One-click full-filter reset regression: passed.
- Existing Missing Photo, Missing Country, Incomplete Profile and Special Category cards retained.
- VERSION.json validation: passed.
- Browser runtime: not tested locally; verify after Staging deployment.

## Deployment

- Candidate published to develop only.
- No database migration, scoring, judge or projector files are changed.
