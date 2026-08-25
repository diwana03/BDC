# BDC v2.3.3-dev411

Release date: 26 August 2026  
Build: 3117  
Branch: `develop`

## Competitor database export

- Adds **Export Competitors CSV** to Competitor Management for Super Admin only.
- Exports every row matching the current search, country, dance, role, division, status, missing-information, sorting and order selections; pagination does not truncate the export.
- Includes BDC ID, exact name, private email and phone, Instagram, country, Bachata and Salsa roles/divisions, points by style, total points, status and record timestamps.
- Generates a UTF-8 Excel-compatible CSV and prevents spreadsheet formula execution from text fields beginning with `=`, `+`, `-` or `@`.
- Records the export row count and active filters in the audit log.
- Does not modify competitor records, profiles, points, results or scoring data.

## Validation

- Focused executable export integration and security assertions.
- Full JavaScript regression suite.
- Repository diff and whitespace validation.
- PHP syntax validation was unavailable locally because this workspace does not provide a PHP binary.

## Migration status

- No database migration required.

## Deployment status

- Candidate for `develop`; Staging runtime download verification is still required.
- Production remains blocked until the exact Staging commit passes Super Admin and non-Super-Admin access checks plus filtered and unfiltered CSV download checks.
