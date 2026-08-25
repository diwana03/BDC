# BDC v2.3.3-dev401

Release date: 25 August 2026  
Build: 3107  
Branch: `develop`

## Deployment migration repair

- Fixes the dev400 Production rollback caused by `20260825_0300_restore_manual_special_categories` dependency-checksum drift.
- Confirms the applied dev399 migration wrapper was not modified; only its reusable recovery service changed in dev400.
- Accepts only the exact dev399 Production checksum and the stable immutable-wrapper checksum.
- Keeps all other unknown migration checksum mismatches blocked.
- Makes both Special Category recovery wrapper migrations file-only checksummed so later service maintenance cannot invalidate applied migration history.
- Retains the complete dev400 targeted pre-audit backup recovery.

## Deployment result expected

1. The already-applied dev399 migration passes compatibility validation without running again.
2. Migration `20260825_0400_special_category_backup_recovery` runs once.
3. The deployment health check completes and dev401 remains active.
4. The operator can preview the pre-dev397 backup from the Special Category Recovery page.

## Validation

- Complete JavaScript regression suite required before publication.
- Dedicated migration checksum regression: `tests/special-category-migration-checksum-v401.js`.
- PHP/database runtime is not available in the Codex workspace.
