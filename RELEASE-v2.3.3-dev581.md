# BDC 2.3.3-dev581

## Recoverable Saved Round archive

- Adds an Archive action to every Saved Round for Super Admin only.
- Moves archived rounds out of the normal Saved Rounds table.
- Adds a separate Archived Rounds view with a Restore action.
- Restores each round to its exact status from before archival.
- Preserves the event, round setup, competitors, judges, judge sessions, scores, calculated results, approvals and official history.
- Applies the same behavior to Live and isolated Test Jack and Jill tables.
- Records every archive and restore action in the admin audit log.

## Validation

- Archive and restore permission, preservation and Test/Live parity regression checks.
- Full JavaScript regression suite.
