# BDC 2.3.3-dev203

## Judge Database administration

- Keeps the existing full administrator editor for judge identity, private contact details, qualifications, status and internal notes.
- Adds JPG, PNG and WebP judge-photo upload to the administrator editor.
- Adds photo removal.
- Adds competitor-style drag, zoom, crop and reposition controls.
- Preserves the original uploaded image for later readjustment without repeated quality loss.

## Database

- Adds immutable migration `20260817_0100_judge_original_photo.php`.
- Adds nullable `bdc_judges.original_photo_url` only when it does not already exist.
- No existing migration or production configuration was modified.

## Validation and deployment

- Source validation: JSON parsing and whitespace checks passed. PHP CLI is unavailable in this workspace, so PHP syntax and the upload/crop workflow must be confirmed by the Staging health check.
- Staging deployment: not performed. Deploy through Release Manager after push.
- Production deployment: not performed.
