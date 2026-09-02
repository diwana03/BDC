# v2.3.3-dev574

## WDC photo route recovery

- Replaces the failing Adjust Photo page with a minimal WDC-only implementation.
- Removes the shared BDC competitor join from the photo page.
- Adds guarded MIME capability detection and converts upload/runtime failures into visible errors instead of blank HTTP 500 pages.
- Updates only `bdc_wdc_identities.photo_url` and retains the WDC audit record.

## Safety

- No migration.
- No automatic data change. A WDC photo changes only after an authorized administrator submits a valid image.
- BDC, SDC, scoring, results and championship points are untouched.

## Validation

- Full JavaScript regression suite: required before publishing.
- GET and upload runtime verification: required after deployment.
