# BDC v2.3.3-dev591

## MCP registered division roster correction

- Stops Rising and Open MCP directory calls from returning the entire active BDC or SDC identity directory.
- Reads Bachata Rising/Open membership from `bdc_competitor_special_categories`.
- Reads Salsa Rising/Open membership from the canonical `bdc_sdc_competitor_categories` table.
- Applies role, name and approved eligibility checks only after confirmed category registration.

## Validation

- Focused registered-category, division-directory, OAuth, MCP and Release Manager safety checks pass locally.
- PHP 8.1 lint and the complete JavaScript regression workflow must pass before merge.
- No database migration or data mutation.
