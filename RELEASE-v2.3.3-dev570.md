# v2.3.3-dev570

## Dance Cup Competitor list 500 hotfix

- Restores the proven WDC identity-first list query shape.
- Replaces compound category metadata aggregation with simple independent category, event, style and level aggregates.
- Removes optional shared-person Instagram and gender fields from the main list runtime path.
- Preserves one row per WDC identity, filters, grouped registrations, points, photo actions and duplicate indicators.
- No database migration or scoring/data change.

## Validation

- Full JavaScript regression suite: PASS.
- Static diff validation: PASS.
- Production runtime verification: pending deployment of this exact candidate.
