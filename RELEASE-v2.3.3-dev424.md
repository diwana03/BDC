# BDC v2.3.3-dev424

Build: 3130  
Date: 2026-08-26

## Staging migration recovery

- Adds the exact dependency-aware checksum recorded when Staging applied the short-lived dev414 form of migration `20260826_0500_publish_verified_form_profiles`.
- Keeps the restored migration file byte-for-byte immutable.
- Keeps file-only checksum calculation for migration 0500 so later service changes cannot invalidate it again.
- Continues to reject every unknown stored checksum and every unknown current migration modification.
- Does not rerun migration 0500 or change competitor data; it only recognizes the already-applied audited historical form.

## Validation

- Historical checksum derived from Git migration and dependency contents: passed.
- Immutable current migration checksum regression: passed.
- Strict unknown-checksum rejection assertions: passed.
- All 57 locally executable Node regressions: passed.
- PHP syntax and actual migration execution require the Release Manager Staging runtime.

## Deployment

- Publish the validated candidate to public `diwana03/BDC` `develop`.
- Deploy to Staging and confirm migrations complete before any Production promotion.
- Production remains blocked until the exact Staging release succeeds.
