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

Run `installBdcTriggers()` once and approve Google Sheets, Drive and external-request access. It installs both the immediate form-submit trigger and a 15-minute reconciliation trigger. The reconciliation trigger safely retries failed rows and catches responses missed during temporary trigger, network or server failures.

The first reconciliation starts at row 2 in batches of 40, so it also backfills historical responses without creating duplicate BDC identities. `syncRowsFrom(firstRow)` remains available for an authorised manual replay. The API's source-key and payload-hash checks make every replay idempotent.

## Identity and category rules

- Exact duplicates are ignored using both the source row key and a canonical payload hash.
- A unique same-role match by email, phone or Instagram reuses the existing BDC ID.
- Leader and Follower remain separate BDC identities even when contact information is shared.
- A single exact-name match is reused when no identifier contradicts it.
- Ambiguous candidates remain in `bdc_form_sync_submissions` with `pending_review` status.
- Open and Amateur categories remain event-registration evidence only.
- New identities start provisionally at Novice and no permanent dance division is changed before Super Admin result publication.
- Photos are auto-oriented when EXIF is available and centre-cropped to an unstretched 800×800 JPEG.
- An inaccessible or malformed Drive photo is logged and skipped without losing the competitor registration.
