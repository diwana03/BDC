# BDC v2.3.3-dev413

Build: 3119  
Date: 2026-08-26

## Verified 28-profile correction

- Publishes the exact Open/Rising style profiles verified against the Amateur and 4th Asia Open source sheets: 8 Bachata Rising, 1 Salsa Rising, 11 Bachata Open and 9 Salsa Open assignments across 28 people.
- Preserves MAMONG as both Bachata Open Leader and Salsa Open Leader.
- Consolidates the newly created duplicates BDC-000549, BDC-000543 and BDC-000528 into the established BDC-000424, BDC-000523 and BDC-000522 identities.
- Moves linked submissions, registrations, results, points, requests and claims to the kept identity; retains useful contact, photo and career-link data before deleting each duplicate.
- Creates a fresh database backup, validates every exact BDC ID/name pair, applies all corrections in one transaction and records data-entry recovery evidence.

## Validation

- Exact manifest regression: passed (28 unique identities, 29 style profiles).
- Category totals regression: passed (8/1 Rising, 11/9 Open).
- Duplicate mapping and MAMONG dual-style regression: passed.
- PHP syntax and database execution: not locally runnable in the connected workspace; required on Staging.
- Competitor Management filters and exact-profile display: not runtime-tested; required on Staging.

## Deployment

- Candidate prepared for the `develop` release line only.
- No scoring, judge or projector files are changed.
- Production promotion remains blocked until the exact commit is deployed to Staging and the post-migration competitor export confirms 28 corrected identities, 29 profiles and no three duplicate IDs.
