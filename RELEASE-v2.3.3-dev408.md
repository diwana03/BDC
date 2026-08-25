# BDC v2.3.3-dev408

Release date: 26 August 2026  
Build: 3114  
Branch: `develop`

## Named test-event-only repair

- Restricts cleanup to the exact J&J test events identified by the Super Admin screenshots.
- Does not scan or repair unrelated events or historical profiles.
- Removes only a Bachata/Salsa profile whose competitor appeared in one of those named test events for the same dance style.
- Protects published publication-linked scoring history, historical imports, manual history, CSV imports, approved corrections and explicit Special Category recovery evidence.
- Rechecks named-event evidence immediately before deletion.
- Creates a fresh database backup and records evidence for every repaired profile.

## Dance Cup

- The shown Dance Cup test cards are not cleanup targets because Dance Cup roster code does not write J&J discipline profiles.
- Their scoring data is not deleted or changed.

## Validation

- Full JavaScript regression suite.
- Focused exact-event allowlist, unrelated-data exclusion and publication/recovery protection assertions.
- Repository diff and version/build checks.
- Staging verification is mandatory before Production.
