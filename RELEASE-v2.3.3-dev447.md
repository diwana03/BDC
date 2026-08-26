# BDC 2.3.3-dev447 · build 3153

## Restore Dance Cup participant rows

- Detects an incomplete result-history schema by required columns, not table name alone.
- Loads reusable Dance Cup participant profiles independently from unpublished history.
- Restores the 16 profiles already counted by the database instead of showing zero cards and an empty table.
- Keeps Published Results and Winning Results at zero until the result-history migration is complete.
- Includes no destructive data operation and no database migration.

## Validation

- Added regression coverage for partial-history schema handling.
- Complete JavaScript regression suite passed.
- Staging deployment and authenticated page verification remain required.
