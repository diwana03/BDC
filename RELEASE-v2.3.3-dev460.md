# BDC 2.3.3-dev460

## Protected Dance Cup category editing

- Adds an Edit action beside each Dance Cup category.
- Allows ordinary category and criteria edits until scoring begins.
- Locks editing after a judge session starts or the first mark is saved.
- Requires an authorised, audited unlock with a reason and exact `UNLOCK` confirmation.
- Temporarily pauses judge scoring and autosave while a category is unlocked for editing.
- Preserves all existing marks for metadata-only changes.
- Requires exact `RESET SCORES` confirmation before changed criteria can clear incompatible marks and calculated results.
- Keeps submitted, pending-approval and approved results behind the existing protected result-reopen workflow.
- Applies the same protections independently to Live and Test Dance Cup data.

No Jack & Jill scoring code or data model is changed by this release.
