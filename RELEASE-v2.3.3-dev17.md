# BDC 2.3.3-dev17

## Production migration compatibility patch

- Preserves the already-applied `dev16` migration and its checksum.
- Detects the actual ID column types in each database before creating the point-adjustment request table.
- Creates matching foreign keys where the existing schema supports them.
- Uses indexed compatibility columns on legacy schemas that cannot accept cross-generation foreign keys, while retaining application validation, atomic approval writes, and the audit trail.
- Includes the migration process exit code and detailed output in Release Manager failures.

No website layout, scoring formula, stored points, participant history, or Production data is changed by this patch.
