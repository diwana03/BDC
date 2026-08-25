# BDC v2.3.3-dev412

Build: 3118  
Date: 26 August 2026  
Branch: `develop`

## Missing competitor recovery preparation

- Reconciles the verified Open and Amateur source rows against the exported 316-row Competitor Database baseline.
- Confirms 28 genuinely absent identities plus Madya as a separate Follower identity; Jaecheol Yun appears in both source forms but represents one identity.
- Keeps the private recovery data outside Git history. Participant emails, phones and Instagram handles are never embedded in a public migration or release source.
- Uses the secured Form Sync runtime for recovery, which reuses any identity created after the export instead of allocating a duplicate.
- Preserves the publication gate: registrations start provisionally at Novice and do not assign Bachata Open, Salsa Open, Bachata Rising or Salsa Rising as a permanent division.

## Future sync reliability

- Adds a scheduled 15-minute reconciliation trigger alongside immediate form submission sync.
- Starts historical catch-up at row 2 in bounded 40-row batches.
- Retains failed rows in a retry queue while allowing later rows to continue.
- Stores the bound spreadsheet ID so time-driven triggers can reliably reopen the correct response workbook.
- Keeps every replay safe through the server's source-key and payload-hash duplicate protection.
- Prevents an inaccessible or malformed Drive photo from blocking the participant identity and contact-data sync.

## Identity correction

- Restricts identifier reuse to the same dance role or a valid `both` identity.
- Prevents a shared email, phone or Instagram value from merging a new Follower into an existing Leader BDC ID or vice versa.
- Keeps Leader and Follower BDC IDs separate as required by BDC operations.

## Validation

- PHP syntax and PHP identity unit test: not locally runnable because this workspace has no PHP CLI; required on Staging.
- Google Apps Script JavaScript syntax: passed through a JavaScript syntax check.
- Scheduled reconciliation and role-safe identity static regression: passed.
- Existing publication-only division gate regression: passed.
- Full JavaScript regression suite: 46 of 46 passed.
- Repository whitespace and `VERSION.json` validation: passed.
- Database-row recovery and verification: performed only through the secured portal runtime; never through public source data.

## Sanity gate

- Competitor creation and Form Sync path traced from Apps Script through the signed API, identity resolution, BDC ID allocation and submission audit record.
- Scoring dashboards, judge forms and projector are not modified by this release.
- Production promotion is blocked until Staging runtime checks and a fresh competitor export confirm recovered identities without duplicate BDC IDs or permanent Special Category changes.
