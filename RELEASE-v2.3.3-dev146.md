# BDC v2.3.3-dev146

Build 2476 · Development release · 13 August 2026

## Registration and profile divisions

- Added **BDC Rising Star** and **BDC Open** to New Registration.
- Added both divisions to Update Existing Profile.
- Updated Admin Competitor Edit and scoring setup screens with the same labels.
- Kept Salsa Rising, Salsa Open, Invitational, Semi Pro, Pro and All Star support.

## Profile-request storage

- Expanded `bdc_profile_requests.current_division` to accept every supported Bachata and Salsa profile division.
- Added a migration for existing installations.
- Ensured approved requests retain the selected special division in the discipline profile.

## Scope

- Registration, profile editing, special-category labels and database schema only.
- No Production deployment or configuration changes.
