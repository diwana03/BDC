# BDC 2.3.3-dev242

## Finalist synchronization repair

- Keeps Semifinal and Final membership synchronized with the source round's current callback results.
- Withdraws previously transferred alternates or eliminated competitors before reactivating confirmed callbacks.
- Removes stale Final pairs, marks and calculated results tied to withdrawn transferred entries.
- Preserves competitors explicitly added by an administrator.
- Applies the same repair to Test and Live scoring, including discipline-routed workflows.

## Super Admin draft deletion

- Restricts complete draft-workflow deletion to Super Admin.
- Allows an unpublished workflow to be deleted when an earlier parent round is already completed.
- Ignores stale round locks for Super Admin draft deletion.
- Cleans automatic judge sessions, rolled-back publication documents and publication points.
- Preserves event and competitor records.
- Keeps published workflows protected until rollback.
- Passes dance style explicitly so Salsa and Bachata workflows cannot be confused.

## Validation and deployment

- Added regression guards for stale callback cleanup, Super Admin authorization and dependent-record cleanup.
- Static release checks passed.
- PHP runtime validation is unavailable in the current workspace and must run on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
