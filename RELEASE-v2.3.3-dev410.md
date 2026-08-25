# BDC v2.3.3-dev410

Release date: 26 August 2026  
Build: 3116  
Branch: `develop`

## Source-verified profile correction

- Compares the suspected profiles with the original SBTA Open and Amateur entry sheets.
- Preserves every profile supported by those sheets, including April Chen, Batta Man and the nine verified Bachata profiles.
- Removes only the exact approved allowlist: 26 unsupported Salsa Open profiles and Andrea Aversa's unsupported Bachata Rising profile.
- Rechecks BDC ID, exact name, dance, division, published history and manual/import history before each deletion.
- Creates a fresh database backup, applies the correction transactionally and records audit evidence while retaining recovery history.
- Does not alter competitors, event entries, scores, results, points, Dance Cup data or unrelated profiles.

## Future rule

- Event registration, Test scoring and unpublished Live scoring remain isolated from permanent profiles.
- Permanent profile progression occurs only after Super Admin publication.

## Validation

- Full JavaScript regression suite.
- Focused exact-allowlist, protected-profile, migration and version assertions.
- Repository diff and whitespace validation.
