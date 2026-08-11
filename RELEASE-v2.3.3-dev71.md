# BDC v2.3.3-dev71

## Scoring Tests Mode Selector + Shared Engine

- Scoring Tests now opens with the same two clear choices as Production: Manual Scoring and Automatic Scoring.
- The selected test mode is stored in the admin session before entering the test workflow.
- Adds `HeatsScoringEngine` as the single storage-agnostic Heats/Semifinal ranking engine.
- Adds `ScoringCalculationService` so Production and Test use the same Heats calculation code while persisting to their own tables.
- Normal Tier 1/2/3 selection is recalculated through shared `ScoringRulesService` from the larger Leader/Follower role count when there is no manual override.
- YES/A1/A2/A3 values are normalized through shared `ScoringRulesService` before ranking.
- Special categories bypass participant-count tiering in the shared calculation path.
- Final Relative Placement continues to use the shared production `RelativePlacementCalculator`.
- Existing legacy dashboard-local `computeResults()` functions remain only as rollback compatibility; normal Generate Results actions are intercepted and sent to the shared engine.
- No test calculation can publish or amend official BDC points through the shared calculation service.
- No Staging or Production deployment is performed by this release commit.
