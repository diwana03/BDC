# dev749: detailed Amateur and past published reports

## Problem and correction

The user reports Amateur links opening Points summaries and asks for the same detailed report format for past reports. The legacy Salsa special publisher assigns its main document to Points. Published category pages use separate refresh handlers.

Published GET pages for Salsa standard, Salsa special and Bachata special now open the common report refresh page. Pending approval and POST publication workflows remain separate. Future publication main report pointers use Final. Existing publication and round pointers are repaired to the existing detailed Final document during successful refresh, inside a transaction, with file backups and restoration on failure.

Refresh prepares, uploads, validates, backs up and replaces only Heats and Final. Points generation remains part of initial publication but is excluded from refresh. Scores, rankings, points ledgers and Points HTML files are unchanged. Direct-to-Final competitions skip nonexistent Heats. Actual advancement and all-judge landscape snapshots use the existing detailed report sources.

## Past reports

Admin Result Repository links to a directory of all published scoring competitions, including Amateur and Open, without a date cutoff. Test and Live directories are isolated. Existing public report filenames are retained. Imported historical documents lacking saved scoring records cannot be reconstructed; originals are retained. No automatic Production backfill has been run.

## Parity Gate

- Test: admin/scoring-tests/publish.php and report-archives.php use isolated round/publication/document tables.
- Live: admin/scoring/publish.php and report-archives.php use Live data. Published special/Salsa pages reach the common handler.
- Shared: PublicationArchiveLookup and published_report_archive_list.
- Projector: unchanged. Existing Heats/Final detailed source pages remain unchanged.

## Validation and status

Executable JavaScript tests run the actual archive generation function for refresh, first publication and preview failure. Integration checks verify publication lookup, protected replacement, link repair and historical directory scope. PHP tests include report-only recovery without a Points document. PHP CLI and Staging runtime verification remain pending; this is a local candidate, not a verified Production repair. No schema migration or external push performed.

Local results: 279 JavaScript checks passed, 0 failed, 2 existing PHP-dependent wrappers blocked. The standalone PHP lookup test is also not executed because PHP CLI is unavailable. Direct-to-Final generation passed the focused executable test. Production report contents have not been changed or runtime-verified.
