# BDC v2.3.3-dev397

Release date: 25 August 2026  
Build: 3103  
Branch: `develop`

## Repair existing category contamination

- Adds an idempotent database migration that repairs legacy `Bachata Rising`, `Bachata Open`, `Bachata Invitational`, `Salsa Rising`, and `Salsa Open` values previously stored as permanent competitor divisions.
- A contaminated Live discipline profile is restored from approved, style-specific and role-specific official result/point history; a dancer with no approved history returns to Novice.
- A contaminated isolated Test competitor mirrors a valid official career division when available and otherwise returns to Novice.
- Normal permanent divisions, manual BDC overrides, event rounds, approved result history, publications and requested event categories are not rewritten.

## Prevent recurrence

- Permanent identity tables now reject every special event category at the schema level.
- Google Form Open/Amateur submissions keep their requested event category in the submission payload while preserving an existing permanent division or creating a Novice profile.
- Manual, Automatic, Salsa, Registration Desk and isolated Test roster additions now pass through the shared approved-history registration gate for both existing and newly created competitors.
- The competitor editor offers permanent career divisions only; special categories remain event-entry choices.

## Mandatory sanity gate

Automated test: `node tests/permanent-division-category-repair-v397.js`

The gate verifies migration repair coverage, schema-level rejection, Google Form preservation, shared roster routing, approved-history derivation, Test/Live parity and the absence of special-category choices from permanent identity editing.

## Database and deployment

- Migration: `20260825_0100_repair_permanent_division_categories`.
- The migration is data-repairing but non-destructive: scoring rounds, entries, scores, results, points and publication history remain unchanged.
- Staging runtime validation: **not tested from Codex**.
- Production: **blocked until the exact develop commit and migration pass Staging runtime validation**.
