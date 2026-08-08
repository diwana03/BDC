# BDC v2.3.3-dev72

## Scoring Test Competitor Generator Fix

- Fixes the Scoring Tests `Generate Competitors` SQL error caused by production competitor columns missing from `bdc_test_competitors`.
- Adds a migration that safely mirrors any currently missing production competitor columns into the disposable test competitor schema.
- Specifically resolves the reported `original_photo_url` missing-column failure.
- Does not modify official competitors, points, progression, publications, or production scoring data.
