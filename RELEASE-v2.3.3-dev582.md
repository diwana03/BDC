# BDC 2.3.3-dev582

## Deployment checksum recovery

- Restores `20260903_0100_wdc_profile_details` to the exact immutable version already applied in Production.
- Moves the later `photo_consent` schema addition into the new `20260903_0110_wdc_photo_consent` migration.
- Preserves all existing WDC profile data.
- Retains the dev581 Super Admin scoring-round Archive and Restore feature.
