# BDC v2.3.3-dev625

## Fix

- Normalizes unambiguous competitor country and city variants to the canonical country list.
- Corrects Livii Iabanzhi from `Japan, Tokyo` to `Japan`.
- Repairs common Japan, South Korea, Thailand, Australia, New Zealand, Indonesia, Philippines, Vietnam, Russia, Taiwan, Italy, France and United States variants found in registered rosters.
- Uses canonical country values in the Bachata and Salsa dashboard filter, administrator profile saves, profile integration imports and flag rendering.
- Expands each dashboard country selection to all stored values that resolve to that canonical country, preventing unlisted legacy variants from disappearing.
- Preserves genuinely ambiguous dual-country values instead of guessing.
- Archives every migrated value in `bdc_country_normalization_archive` for recovery.

## Validation

- Canonical-country behavioral regression: pending.
- Full PHP 8.1 lint and JavaScript regression gate: pending.
- Staging runtime verification: not performed and required before Production promotion.

## Database

- Migration: `20260904_0200_normalize_competitor_countries.php`.
- Only exact known malformed values are updated.
- No scores, points, categories, roles, event rosters or identities are changed.

## Deployment

- Source candidate only. Deploy the merged `develop` commit to Staging through Release Manager after all mandatory gates pass.
