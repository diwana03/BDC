# BDC Portal 2.3.3-dev561

## Jack & Jill council isolation and publication-only progression

- Bachata Jack & Jill accepts active BDC profiles only.
- Salsa Jack & Jill accepts active SDC profiles only.
- Dance Cup event packages accept active WDC identities only.
- Manual, Automatic, Test, Registration Desk and signed API entry paths share the same server-side council gate.
- Scoring cannot create a new BDC, SDC or WDC identity.
- Draft rosters, submitted rosters, Test scoring, rejected approvals and unpublished scores do not assign Novice or change permanent progression.
- Super Admin publication activates Novice for every active first-time participant, including non-finalists and zero-point competitors.
- Approved Salsa publication updates the canonical SDC progression table without changing Bachata/BDC progression.
- Rising, Open and Invitational remain event categories and are not stored as permanent progression divisions.

## Verification

- Added cross-council, inactive-profile, API discipline, Test-isolation and publication-gate regression coverage.
- Full JavaScript regression suite passes.
- PHP lint was unavailable in the workspace; source integrity, release checks and static regression gates were used.

Production: **not deployed by this release file**.
