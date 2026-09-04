# BDC v2.3.3-dev624

## Fix

- Uses the canonical `bdc_competitors.bdc_id` source throughout the Bachata competitor dashboard.
- Restores every valid BDC profile that was hidden only because it lacked a legacy `bdc_result_identities` mirror, including Livii Iabanzhi (`BDC-000612`).
- Aligns the visible list, name and BDC ID search, filters, pagination, CSV export, and summary counts to the same canonical identity boundary.
- Leaves Salsa/SDC identities, categories, scoring, event rosters, judge workflows, and production data unchanged.

## Validation

- Focused canonical dashboard identity regression: passed.
- Full repository PHP 8.1 lint and JavaScript regression gate: passed in PR #14.
- Staging runtime verification: not performed; required before Production promotion.

## Database

- No migration.
- No data mutation.

## Deployment

- Source candidate only. The user deploys the exact merged `develop` commit to Staging through Release Manager after all mandatory gates pass.
