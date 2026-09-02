# BDC Portal 2.3.3-dev564

## Bachata registration and Salsa-only identity reconciliation

- Adds a read-only `bdc_members` diagnostic for exact BDC identity, Bachata role, progression, special categories, photo presence, result count, point-transaction count and total points.
- Adds approval-gated `bdc.update` proposals for exact Bachata Rising, Open and Invitational registrations from verified forms.
- Allows progression to be reset only to Unknown and only when both Bachata result history and Bachata point-transaction history are empty. Registration never manufactures Novice progression.
- Extends protected BDC identity detachment to verified Salsa-only SDC members. It preserves the shared person, photo, SDC and WDC records and removes only the unused BDC identity and Bachata-scoped compatibility profile/categories.
- Archives the prior BDC identity, Bachata discipline profile and Bachata categories before detachment so the operation is recoverable.
- Keeps all proposed updates and detachments pending until explicit Super Admin approval. The signed API cannot approve or directly commit them.

## Source reconciliation baseline

- Compared 222 populated registrations across the verified 2026 Amateur and Open response sheets, representing 196 normalized names.
- Classified style from the actual Lead/Follow competition selection, not the fee description.
- Found 47 exact Salsa-only BDC matches with zero Bachata results and zero Bachata point transactions; these are eligible for approval-gated detachment after deployment.
- Protected Joshua yao (`BDC-000230`) because the corrected production audit found 12 Bachata history records; current Salsa-only registration cannot erase prior BDC history.
- Excluded ambiguous names, unmatched names and six selection/fee mismatches from automatic action.

## Validation

- Focused BDC registration reconciliation regression: passed.
- Existing API change-proposal, profile-diagnostic, SDC isolation, council isolation and WDC integration regressions: passed.
- Full JavaScript regression suite: passed.
- PHP lint: unavailable in this workspace because PHP is not installed; required during deployment before runtime approval.
- Staging runtime verification: pending user deployment from `develop`.
- Production deployment and data proposals: not performed by this source release.

## Scope and safety

This release changes no scoring tables, results, point transactions, placements, leaderboards or published history. Deployment alone changes no competitor data. Every data correction is separately fingerprinted, reviewed and approved in the Super Admin API Changes panel. Production promotion remains blocked until the exact candidate passes server-side PHP lint and authenticated Staging API smoke tests.
