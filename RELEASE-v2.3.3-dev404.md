# BDC v2.3.3-dev404

Release date: 25 August 2026  
Build: 3110  
Branch: `develop`

## Historical profile correction

- Runs the dev403 unapproved Salsa-profile repair automatically as a one-time deployment migration.
- Creates a fresh database safety backup before deleting any candidate profile.
- Removes only ordinary Salsa profiles connected to Test or Live scoring activity with no approved Salsa participant result and no approved Salsa points transaction.
- Never removes Special Category assignments or published Salsa history.
- Rechecks every candidate inside the repair transaction and records evidence for every removed profile.
- Aborts the migration and deployment if backup or repair fails.

## Future rule retained

- Test, draft, submitted, rejected and unpublished activity cannot create or change permanent Bachata or Salsa profiles.
- Only Super Admin approval and publication synchronizes a permanent discipline profile.

## Validation

- Full JavaScript regression suite.
- Focused deployment-repair, backup, evidence and publication-boundary assertions.
- Repository diff and version/build checks.
- PHP/database runtime is unavailable in the Codex workspace; the exact migration result count must be verified on Staging before Production.

## Deployment status

- Candidate targets `develop` only.
- Production remains blocked until Staging migration, repair evidence and protected published-profile checks pass.
