# BDC 2.3.3-dev18

## Production migration checksum repair

- Fixes the actual Production deployment blocker before the point-adjustment migration runs.
- Recognizes only the two verified historical checksums for migration `20260803_2200`.
- Keeps checksum validation fail-closed for every unknown or edited migration.
- Retains the `dev17` legacy-ID compatibility preflight and detailed Release Manager errors.
- Requires no command-line database repair or manual Production changes.

No website layout, scoring formula, stored points, participant history, or Production data is changed by this patch.
