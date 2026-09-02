# v2.3.3-dev571

## Production WDC page recovery

- Replaces the failing Dance Cup Competitor SQL with the exact WDC directory query successfully executed through the Production signed diagnostics API.
- Performs name, ID, country, registration, entry-type and completeness filtering in PHP after the proven read.
- Preserves one WDC identity per row, active registration visibility, Edit WDC and universal photo adjustment.
- No database migration or data mutation.

## Validation

- Production signed WDC query: PASS.
- Full JavaScript regression suite: required before publishing.
- Runtime page verification: required after deployment.
