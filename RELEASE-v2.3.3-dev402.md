# BDC v2.3.3-dev402

Release date: 25 August 2026  
Build: 3108  
Branch: `develop`

## Official data-entry reconciliation

- Adds a Super Admin reconciliation workflow to the existing Special Category Recovery page.
- Accepts untouched CSV exports from the official Amateur J&J and 4th Asia Open Google Forms.
- Counts unique competitors deterministically and creates style-specific Bachata and Salsa assignments from the submitted category selection.
- Gives an Open entry precedence over an Amateur entry for the same competitor and dance style.
- Matches exact competitor names first and uses email only when no exact-name match exists, preventing shared booking emails from merging different dancers.
- Reports unique people, style assignments, matched people, missing assignments, already-special assignments, unmatched entries and duplicate conflicts before any write.
- Restores only matched competitors whose current style division is not already a Special Category.
- Creates a fresh Production database safety backup and writes an auditable evidence row for every restored assignment.
- Does not commit spreadsheet names, emails or response data to the public repository.

## Sanity gate

- Existing audit and database-backup recovery remain unchanged.
- Existing correct Special Category assignments are preserved.
- Ambiguous database matches are blocked instead of guessed.
- Repeated upload and restore is idempotent because already-special assignments are skipped.
- This is an Admin competitor-data recovery change; Testing/Live scoring and projector paths are not modified.

## Validation

- Full JavaScript regression suite.
- Focused integration assertion: `tests/special-category-data-entry-reconciliation-v402.js`.
- Repository diff and version/build validation.
- PHP/database runtime is unavailable in the Codex workspace; Staging runtime verification is required before Production promotion.

## Migration status

- No database migration. The existing recovery evidence schema is reused.

## Deployment status

- Candidate targets `develop` only.
- Production is blocked until the exact commit is deployed to Staging and the two official CSV exports preview the expected unique count without unresolved conflicts.
