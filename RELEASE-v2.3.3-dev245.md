# BDC 2.3.3-dev245

## Public points result cleanup

- Hides placements worth zero BDC points from the public Participant Results table.
- Hides zero-point rows from public competitor competition histories and statistics.
- Applies the filter in SQL so search results, event filtering and public totals remain consistent.
- Keeps the complete Final ranking, non-point finalist history and publication audit data internally.
- Does not change BDC point schedules or previously awarded points.

## Scope

- Public Bachata and Salsa participant result surfaces are covered.
- No database migration.
- No Production deployment.
