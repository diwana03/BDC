# BDC v2.3.3-dev67

## Automatic Scoring Test / Production Parity

- Adds `ScoringRulesService` as the shared source for BDC tier thresholds and Heats YES / A1 / A2 / A3 weights.
- Adds an Automatic Scoring Production Parity test inside the Scoring Tests module.
- Tests normal BDC participant tiers from the larger Leader/Follower role count.
- Tests Bachata Rising, Bachata Open and Bachata Invitational using the production `SpecialCategoryService` fixed-point schedules.
- Tests the production Automatic Judge Browser service contract.
- Tests Final ranking through the production `RelativePlacementCalculator`.
- Adds an `Automatic Parity Test` navigation button to the existing Scoring Tests dashboard.
- The legacy random Test Dashboard remains available. Its older copied Heats generator constants are explicitly not treated as the production source of truth; the new parity screen is the authoritative cross-check while those legacy helpers are refactored separately.
- No Staging deployment is performed by this release.
