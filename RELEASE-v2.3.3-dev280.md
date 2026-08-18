# BDC 2.3.3-dev280 · Build 2986

## Final judge ranking review

- Adds a compact **View My Ranking · X/N** control to the Final judge scoring toolbar.
- Shows each placement and its assigned Leader + Follower bib numbers in a clear row-and-column table.
- Uses one continuous table on mobile and two balanced side-by-side tables on larger screens.
- Clearly marks missing placements and keeps the completion count current as rankings change.
- Lets a judge tap an assigned row to return directly to that ranked couple without editing scores inside the popup.

## Parity Gate

- **Testing Score Dashboard:** shared Automatic Final judge link and ranking-depth configuration checked.
- **Live Scoring Dashboard:** shared Automatic Final judge link, saved marks, duplicate-rank movement, and submission locking checked.
- **Projector / Live Scoreboard:** unaffected; the review is private to the judge link and exposes no ranking data publicly.

## Validation

- Candidate/static: JSON validation, whitespace validation, responsive popup regression checks, shared judge-screen review, and Test/Live source parity completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
