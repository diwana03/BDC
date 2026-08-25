# BDC v2.3.3-dev405

Release date: 25 August 2026  
Build: 3111  
Branch: `develop`

## Deployment checksum repair

- Fixes Staging and Production migration failure for the already-applied `20260825_0100_repair_permanent_division_categories` migration.
- Accepts only the exact verified original, dev403 dependency-aware and stable wrapper-only checksums.
- Moves this immutable migration wrapper to stable file-only checksumming so future reusable service changes cannot invalidate an applied migration.
- Keeps every unrelated migration checksum protected and fail-closed.

## Historical profile repair

- Retains the dev404 one-time backed-up repair of ordinary Salsa profiles created by unpublished Test or Live activity.
- Published Salsa history and Special Categories remain protected.

## Validation

- Full JavaScript regression suite.
- Focused migration checksum and historical profile-repair assertions.
- Repository diff and version/build checks.
- Staging must pass migration and repair verification before Production is retried.

## Deployment status

- Candidate targets `develop` only.
- Production is blocked until dev405 succeeds on Staging.
