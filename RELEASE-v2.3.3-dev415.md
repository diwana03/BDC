# BDC v2.3.3-dev415

Build: 3121  
Date: 2026-08-26

## Migration integrity correction

- Restores applied migration 20260826_0500_publish_verified_form_profiles byte-for-byte to Git blob 0b5c98701a22ff450df4f575477852bcff65f36c.
- Removes the dev414 checksum blocker without weakening or bypassing MigrationRunner integrity protection.
- Keeps all progression and Special Category separation work in the new forward-only 0600 migration.
- Supports databases where dev413 already ran: 0600 moves Open/Rising into the separate field and recalculates career progression from approved point and result history.

## Validation

- Immutable 0500 Git blob verification: passed.
- Verified 28-person manifest regression: passed.
- Separate progression/Special Category regression: passed.
- VERSION.json validation: passed.
- Production runtime deployment: not tested; previous attempt rolled files back automatically and retained its database backup.

## Deployment

- Candidate published to develop only.
- Deploy this exact dev415 commit through Release Manager.
- Production approval remains dependent on successful migration, health check and Melissa/28-profile verification.
