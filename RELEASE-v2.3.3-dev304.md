# BDC 2.3.3-dev304 · build 3010

## Google Forms direct sync

- Adds a signed, server-side Google Form response endpoint at `/api/form-sync/`.
- Reuses a clear existing BDC identity by exact name and relevant identifiers.
- Leaves conflicting or ambiguous identities in a visible **Google Form Sync → Pending Review** queue.
- Creates one BDC identity for a new participant and separate Bachata/Salsa discipline profiles under that ID.
- Maps 4th Asia Open to Bachata Open / Salsa Open and 1st Asia Amateur to Bachata Rising / Salsa Rising.
- Ignores payment, payment proof, consent and timestamp fields.
- Auto-orients and centre-crops Drive photos to an unstretched 800×800 JPEG.
- Prevents old and exact-duplicate rows from being processed twice using source keys and canonical hashes.
- Records failed rows without advancing them as completed.
- Includes an installable Apps Script trigger and setup documentation for both response spreadsheets.

## Security and deployment

- Requests require an HMAC-SHA256 signature generated from a 32+ character environment secret.
- The secret is never committed to source or stored in `config/config.php`.
- Production remains unchanged. Deploy this candidate to Staging, configure the secret, run migrations and test one controlled response before promotion.

## Validation

- PHP syntax and unit checks: pending because PHP CLI is unavailable in the current workspace.
- Source inspection: completed.
- Database migration: pending Staging Release Manager.
- Staging runtime workflow: pending deployment.

## Scoring parity

- Not applicable. No Test scoreboard, Live scoring or projector file is changed.
