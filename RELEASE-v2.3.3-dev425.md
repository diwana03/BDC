# BDC v2.3.3-dev425

Build: 3131  
Date: 2026-08-26

## Staging migration isolation

- Prevents the Production-only 28-profile publication migration `20260826_0500_publish_verified_form_profiles` from replaying against the intentionally incomplete Staging competitor dataset.
- Records the immutable file-only checksum in Staging migration history so the migration remains stable on later releases.
- Leaves migration 0500 byte-for-byte unchanged.
- Leaves Production behavior unchanged: Production still verifies an existing approved checksum or executes the protected data publication when genuinely unapplied.
- Does not skip any schema migration or any other data migration.

## Validation

- Staging-only environment gate regression: passed.
- Exact single-migration allowlist regression: passed.
- Staging checksum-record insertion regression: passed.
- Production execution path preservation regression: passed.
- Immutable migration 0500 identity guard regression: passed.
- All 58 locally executable Node regressions: passed.
- PHP syntax and actual Staging migration execution require Release Manager runtime validation.

## Deployment

- Publish the validated candidate to public `diwana03/BDC` `develop`.
- Deploy dev425 once to Staging and confirm the migration log records `20260826_0500_publish_verified_form_profiles [staging data publication skipped]` before health check passes.
- Production remains blocked until that exact Staging candidate succeeds.
