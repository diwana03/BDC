# BDC v2.3.3-dev144

Build 2474 · Development release · 13 August 2026

## Automatic Testing workflow repair

- Fixed HTTP 404 responses from Preview, Save, Submit and callback-routing actions by posting to the Test dashboard page already loaded in the embedded screen.
- Added **Save Scores Draft**.
- Added **Generate Automatic Scores Draft** to create valid editable scores without submitting or locking judge sessions.
- Kept **Generate & Submit All Judges** as a separate one-click completion option.
- Added **Print Judge Sheets**.
- Added **Preview / Print Scores** before and after submission.

## Scope

- Testing dashboard and Test automatic judge service only.
- No Production deployment or configuration changes.
