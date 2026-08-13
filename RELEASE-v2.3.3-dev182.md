# BDC 2.3.3-dev182

## Summary

- Fixes `Unknown column 'dance_style'` when saving **Edit Draft Details**.
- Updates `bdc_registration_desk_links` using dance style and division.
- Leaves legacy Registration Desk activity unchanged because it has no dance-style field and cannot safely distinguish Bachata from Salsa history.
- Keeps the edit transaction atomic, so a failure cannot partially update the event or scoring round.

## Validation

- Confirmed the Registration Desk activity schema has no `dance_style` column.
- Confirmed the corrected query no longer references that missing column.
- Confirmed Testing remains isolated and uses the same shared draft editor without Live Registration Desk mutations.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI is unavailable in this workspace; saving a draft must be exercised on Staging after deployment.

## Database migrations

- No migration added.
- No destructive database operation.

## Deployment

- Source release only. The user deploys `develop` to Staging through Release Manager.
- Production deployment is not performed by the coding agent.
