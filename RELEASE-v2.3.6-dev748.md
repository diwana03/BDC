# dev748: audited legacy publication document lookup

## Baseline and scope

The user reports archive refresh failing on deployed dev747. Source inspection shows the Salsa special publisher writes exact document IDs to the salsa_special_approved audit record but does not populate publication_documents. The standard refresh requires that mapping. Actual server database rows have not been inspected.

## Correction

When mappings are missing, recover document IDs only from the approval audit for the same round and publication. Verify published status, event, category and scoring-engine source. Reject missing, conflicting or invalid records before any file backup or replacement. Existing mapped documents retain their lookup. No database writes or migrations are added. Scores, points and projector state are unchanged. Existing file backups and restoration remain in effect.

## Parity Gate

Testing: admin/scoring-tests/publish.php invokes shared PublicationArchiveLookup using isolated test audit/publication/document tables.
Live: admin/scoring/publish.php invokes the same service using live tables.
Projector: unchanged; this lookup affects repository HTML refresh only.

## Validation and deployment

Focused JS integration test and executable PHP mocked-PDO test added. The PHP test covers existing mappings, missing mappings, wrong publication, conflicts, invalid documents and Test/Live selection.
JavaScript regression results: 278 passed, 0 failed, 2 blocked due to missing PHP. The new PHP test is also not executed for that reason. Diff whitespace checks passed.
PHP CLI and Staging browser validation remain unavailable locally. This is an unverified runtime candidate, not a verified Production fix. No push or deployment performed. Production promotion remains blocked pending exact-candidate Staging verification.
