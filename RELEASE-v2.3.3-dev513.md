# BDC v2.3.3-dev513

## Fix

- Restores **Save Competitor & Next** in the Dance Cup judge console.
- The dev511 save handler was updated, but the page still requested the historical `dance-cup-judge-live.js?v=457` cache key. Browsers could therefore render the newer navigator with an older script that did not listen for its save request.
- The active judge save script is now served with cache key `v=513`. A competitor advances only after the matching draft save succeeds.

## Parity Gate

### Candidate and static validation

- Testing Score Dashboard: shared `judge-scoring.php?data_mode=test` render path.
- Live Scoring Dashboard: same shared `judge-scoring.php` render path.
- Judge save endpoint: unchanged. Draft marks continue to use the isolated Test or Live table prefix.
- Live Scoreboard and projector: not affected because this does not alter marks, ranking, projection state or displayed contestant data.
- Database migration: none.

### Staging and runtime validation

- Not runtime-tested. The Cloud Browser infrastructure timed out while listing tabs and opening a fresh tab.
- Production promotion remains blocked until this exact candidate is deployed to Staging and Save Competitor & Next is confirmed through a judge link.

## Deployment Status

- Unverified diagnostic candidate authorised by the user for the `develop` release line.
