# v2.3.3-dev572

## Comprehensive WDC dashboard restoration

- Keeps the exact WDC identity query proven against Production in dev571.
- Restores event, category, style, level, completeness and duplicate filtering.
- Restores case-insensitive search, sortable columns, pagination and Super Admin CSV export.
- Restores championship-point and published-result summaries plus Edit WDC and Adjust Photo on every row.
- Loads registration and result summaries through independent fail-safe reads so an optional summary failure cannot blank the directory.

## Safety

- Read-only dashboard change. No database migration or data mutation.
- BDC, SDC, scoring and result publication workflows are unchanged.

## Validation

- Focused WDC dashboard and integration regression checks: required before publishing.
- Full JavaScript regression suite: required before publishing.
- Production runtime verification: required after deployment.
