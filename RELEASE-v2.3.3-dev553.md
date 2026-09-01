# BDC 2.3.3-dev553

- Adds a signed, paginated, case-insensitive competitor directory with BDC/SDC identity, Salsa profile/category and photo-presence fields.
- Adds allowlisted read-only diagnostics for SDC membership, missing photos, competitor history and deletion-impact simulation.
- Adds exact-ID SDC roster reconciliation planning with Novice-tag detection and official Salsa history blockers.
- The new endpoints cannot approve, submit, commit, delete or mutate production. Deletion simulation returns a plan hash; final action remains authenticated Super Admin work.
