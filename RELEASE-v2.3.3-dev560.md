# BDC 2.3.3-dev560

## SDC database separation

- Adds canonical `bdc_sdc_competitors` and `bdc_sdc_competitor_categories` tables.
- Keeps shared name, photo, country and contact data linked through the existing person record.
- Moves SDC ID, Salsa role, progression, membership status and Rising/Open/Invitational categories behind `SdcCompetitorService`.
- Routes the Salsa dashboard, competitor editor, diagnostics, reconciliation and Super Admin-approved API changes to the SDC tables.
- Retains Salsa-scoped compatibility rows during migration so older scoring paths do not fail during rollout.
- Does not modify BDC result, scoring, point, event or WDC tables.
- Removes the Salsa reconciliation search placeholder collision under native PDO.

## Deployment

Run the normal authenticated database migration after deploying the code. The migration is idempotent and backfills existing SDC members without deleting legacy rows or touching BDC/WDC competition history.
