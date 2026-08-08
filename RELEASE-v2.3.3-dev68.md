# BDC v2.3.3-dev68

## Scoring Test Sandbox Consolidation

- Replaces the normal Scoring Tests Dashboard entry with a fast production-parity sandbox.
- Adds visible Manual and Automatic scoring-mode selection.
- Reads real BDC competitors, BDC IDs, current divisions and production history/points as read-only reference data.
- Stops copying production competitor profile columns into the test competitor table, eliminating schema-drift failures such as `original_photo_url` missing from `bdc_test_competitors`.
- Test scoring writes are restricted to `bdc_test_*` tables through `TestScoringGuardService`.
- Official point transactions, participant results, competitor progression, official publications and production scoring tables are blocked from Test mode writes.
- Normal Heats tiering and YES/A1/A2/A3 weights come from shared `ScoringRulesService`.
- Bachata Rising, Bachata Open and Bachata Invitational fixed-point schedules come from production `SpecialCategoryService`.
- Automatic test mode simulates independent judge-browser submission state while using shared BDC scoring weights.
- Final tests call the production `RelativePlacementCalculator` directly.
- `Finish Test & Clear All Test Data` removes the complete sandbox workflow while preserving all official BDC data.
- Previous Test Dashboard remains temporarily available with `?legacy=1` for validation only.
- Release Manager and staging deployment workflow are unchanged.
