# BDC 2.3.6-dev728

## Change

- Fixes the Automatic Jack & Jill roster identity column so Salsa displays SDC ID and Bachata displays BDC ID.
- Resolves each Live Salsa roster identity from the active `bdc_sdc_competitors` profile instead of the shared legacy BDC field.
- Uses the correct council-specific column heading in both Live and isolated Test scoring.
- Existing event rosters, bibs, scores and competitor records are unchanged.

## Validation

- Static regression checks cover Salsa SDC lookup, dynamic SDC or BDC headings, council-correct row values and missing-ID visibility.
- PHP runtime is unavailable in this workspace, so PHP syntax and browser rendering remain Not Runtime-Tested until deployment to Test.
- Deployment: local source candidate only. Production is unchanged and no push was performed.
