# BDC Portal 2.3.3-dev562

## Protected WDC registration migration

- Adds `wdc_identity` batches to the existing signed Profile Integration API.
- Stages permanent WDC identities and their event/category registrations without creating scores, results, placements or championship points.
- Preserves one solo WDC identity across multiple categories while keeping separate WDC identities for couples and teams.
- Links solo WDC identities to an existing shared person/photo only after exact server-side resolution; it does not create or modify BDC or SDC identities.
- Adds read-only `wdc_members` and `person_match` diagnostics. `person_match` accepts exact registration identifiers but returns no private contact data.
- Adds a WDC tab to Integration Review. No staged WDC row changes live data until an authorised administrator explicitly approves it.
- Stores approved category registrations in `bdc_wdc_registrations` with an event/identity/category uniqueness key.
- Adds registered-category totals and fields to the WDC competitor directory/export.
- Includes a reviewed SBTA 2026 fixture containing 23 permanent identities and 24 unique category registrations. Incomplete Hayan Jaguar and Joan Teh partner entries remain excluded.

## Validation

- Focused WDC integration, council isolation and segmented-dashboard regression checks pass.
- Full JavaScript regression suite passes.
- PHP lint is not available in this workspace; PHP source integrity and diff checks were used.
- Production API directory authentication passed read-only checks. WDC staging cannot begin until this exact migration/API candidate is deployed.

## Deployment status

- Candidate only. Not deployed by this release file.
- After deployment, run read-only `person_match` for duplicate shared profiles, submit the WDC batch, and require Super Admin approval before verifying 23 WDC identities and 24 registrations.
