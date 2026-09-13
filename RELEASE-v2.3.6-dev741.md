# BDC 2.3.6 dev741

## Salsa special publication binding repair

- Fixes `SQLSTATE[HY093]: Invalid parameter number` when Super Admin approves a Salsa Rising, Open or Invitational Final publication.
- Uses separate `published_by` and `approved_by` parameters in the final publication update instead of reusing one named parameter under native PDO prepares.
- Applies the same correction to both Salsa publication routes.
- Preserves the existing finalist identities, scores, final rankings, fixed points and approval state.
- Performs no database migration and no automatic Production mutation.
- Preserves the requested Test and Live code parity.

## Safety and validation

- The existing transaction rolls back the failed approval, so the Production Final remains pending approval with no partial points publication.
- Focused regression: `tests/salsa-special-publication-bindings-v741.js`.
- Full JavaScript regression suite: 272 passed, 0 failed.
- Two known PHP wrapper checks remain environment-blocked because PHP CLI is unavailable in this workspace.

## Deployment

- Staging/Test must confirm that Live-equivalent Salsa Final round review loads all finalist rows and progression buckets without HY093.
- Production remains untouched until the exact Staging-tested commit receives separate Production approval.
