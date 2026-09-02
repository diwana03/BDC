# BDC Portal 2.3.3-dev563

## Dance Cup participant identity correction

- Replaces the legacy profile-request and BDC competitor query on Dance Cup Participants with `bdc_wdc_identities` and `bdc_wdc_registrations`.
- Displays permanent WDC IDs instead of linked BDC person IDs.
- Keeps shared person photos, country, Instagram and gender as display-only profile details without changing BDC or SDC records.
- Shows every approved WDC identity/category registration once, including separate valid categories for the same identity.
- Searches names, WDC IDs, countries and category names case-insensitively.
- Routes edits to the WDC competitor editor; shared solo photos may still use the existing photo-adjustment workflow.
- Adds a protected `bdc.detach_identity` proposal for verified Dance Cup-only people. It archives and removes an unused legacy BDC identity only after confirming there is no official Bachata result or point history, while preserving the shared person, photo, SDC and WDC records.

## Validation

- Focused Dance Cup participant regression: passed.
- Full JavaScript regression suite: passed, 165 of 165.
- PHP lint: unavailable in this workspace because PHP is not installed.
- Production deployment: pending user deployment from `develop`.

## Scope

Deployment itself changes no competitor identity or competition data. It does not change Dance Cup scoring, results, championship points, SDC identities or approved WDC registrations. A legacy BDC identity changes only when a separate `bdc.detach_identity` proposal passes every safety check and is explicitly approved by a Super Admin.
