# BDC v2.3.3-dev327

## Print regression fix and Automatic fallback forms

- Prevents the global premium-navigation layer from removing or relocating logos embedded inside print sheets, PDF reports and presentation documents.
- Restores the intended report header grid: BDC logo left, event title centred and Chief Judge/date metadata right.
- Displays an uncalculated Draft Result total as `—` instead of the false value `0.0`.
- Adds **Print Manual Backup Judge Forms** to Automatic Heats and Semifinal workflows in both Test and Live.
- Keeps the established **Print Final Judge Sheets** paper fallback available for Automatic Finals.
- Reuses the saved competitors, bibs, rounds, judge order, Chief Judge and scoring configuration; no duplicate scoring workflow or database migration is introduced.

## Validation

- Candidate/static: shared branding JavaScript syntax passed.
- Candidate/static: print-logo ownership, null-total handling, Automatic fallback links, Test/Live parity, version JSON and whitespace checks passed.
- Candidate/static: existing manual judge-sheet generator remains shared by Manual and Automatic workflows.
- Database migration: not required.
- Staging/runtime: pending user deployment of this exact `develop` commit and browser print-preview verification.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test result report and Live result report: logo protection and null-total display checked.
- Test Automatic Heats/Semifinal and Live Automatic Heats/Semifinal: manual backup form links checked.
- Test Automatic Final and Live Automatic Final: existing paper judge-sheet generator checked.
- Test and Live print generators: roster, bib, judge order, Chief Judge and round-source queries checked.
- Projection and audience projector: scoring/presentation behavior unchanged.
