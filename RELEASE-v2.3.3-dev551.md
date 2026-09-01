# BDC 2.3.3-dev551 · build 3257

## Competitor dashboard separation

- Adds **Bachata J&J Competitor** and **Salsa J&J Competitor** beneath Jack & Jill.
- Adds **Dance Cup Competitor** beneath Dance Cup.
- Keeps the existing combined **Competitors** dashboard during migration.
- Scopes Bachata and Salsa lists, identities, profiles, points, filters, sorting and CSV exports to BDC and SDC respectively.
- Ensures saving a scoped Jack & Jill profile creates or reuses its permanent BDC or SDC identity.
- Adds the WDC directory and editor for solo, couple, duo, Pro-Am and team entries, with immutable WDC IDs and published championship-point totals.
- Adds migration `20260901_0300_wdc_competitor_directory.php` for WDC country and photo fields and safe solo-profile backfill.

## Validation

- Full JavaScript/static regression suite: passed.
- `git diff --check`: passed.
- PHP CLI/runtime validation: unavailable in this workspace.
- Staging migration and authenticated browser testing are required before Production approval.

## Deployment status

Production is not changed or approved by this release work. Deploy to Staging, run migrations, then verify the three directories before any Production deployment.
