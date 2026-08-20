# Google Forms to BDC sync

This integration sends each new Google Form response directly to BDC. It never reads payment fields, payment proof, consent or timestamps.

## Server setup

1. Deploy this release to Staging.
2. Set `BDC_GOOGLE_FORM_SYNC_SECRET` to a random secret of at least 32 characters in the Staging server environment.
3. Run migrations from Release Manager.
4. Verify `POST /api/form-sync/` rejects an unsigned request with HTTP 401.

## Google Sheet setup

For each response spreadsheet, open **Extensions → Apps Script**, paste `Code.gs`, and add these Script Properties:

- `BDC_SYNC_URL`: the environment endpoint ending in `/api/form-sync/`
- `BDC_SYNC_SECRET`: the same server secret
- `BDC_FORM_KIND`: `open` for 4th Asia Open or `amateur` for 1st Asia Amateur

Run `installBdcTrigger()` once and approve Google Sheets, Drive and external-request access.

For existing rows, run `syncRowsFrom(78)` on the Open sheet or `syncRowsFrom(61)` on the Amateur sheet after Staging verification. The API's source-key and payload-hash checks make this safe to repeat.

## Identity and category rules

- Exact duplicates are ignored using both the source row key and a canonical payload hash.
- A unique match by email, phone or Instagram reuses the existing BDC ID.
- A single exact-name match is reused when no identifier contradicts it.
- Ambiguous candidates remain in `bdc_form_sync_submissions` with `pending_review` status.
- Open registrations map to Bachata Open / Salsa Open.
- Amateur registrations map to Bachata Rising / Salsa Rising.
- Both styles are attached to one BDC competitor through separate discipline profiles.
- Photos are auto-oriented when EXIF is available and centre-cropped to an unstretched 800×800 JPEG.
