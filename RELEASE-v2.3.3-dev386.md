# BDC v2.3.3-dev386

## Automatic Final judge save-response repair

- Detects when an open Live or isolated Test judge page is using a refreshed, regenerated or expired secure link and instructs the judge to open the latest link.\n- Forces all remaining Final judge autosave responses to discard buffered HTML or warnings before returning JSON.
- Extends newly generated and regenerated Test and Live judge links from 12 hours to 72 hours.\n- Handles HTTP 429 and interrupted non-JSON responses with a clear retry message instead of exposing a raw JSON parser error.
- Adds explicit JSON request headers on Final judge autosave.
- Stops Final pairing polling after all couples are synchronized.
- Reduces Automatic Final matrix polling from three to five seconds and handles rate limiting quietly.
- Keeps saved ranks, duplicate-rank validation, submission locking, Relative Placement and projector data unchanged.
- No database migration. Production untouched pending Staging validation.

## Parity Gate

- Candidate/static: Live judge save, Test judge save, Testing dashboard, Live dashboard and both active polling assets checked.
- Runtime: Staging must verify ten sequential rank selections, duplicate-rank movement, NO RANK clearing, dashboard live update, submit/lock and full-refresh persistence.
