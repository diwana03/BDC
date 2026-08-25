# BDC v2.3.3-dev403

Release date: 25 August 2026  
Build: 3109  
Branch: `develop`

## Super Admin publication profile gate

- Allows competitors to be used in Test or Live events without changing their permanent Bachata or Salsa profile.
- Prevents roster creation, Automatic setup, Salsa discipline setup and Google Form registration from creating or changing permanent discipline divisions.
- Keeps Test-created competitor identities inside isolated Test tables.
- Creates or synchronizes the relevant discipline profile only inside the Super Admin approval and publication transaction.
- Keeps Bachata and Salsa profile changes style-specific.

## Old-data repair

- Adds a Super Admin preview listing Salsa profiles linked to Test or Live scoring activity with no approved Salsa participant result or points ledger.
- Excludes Special Category assignments and every profile supported by approved Salsa history.
- Rechecks approved history immediately before each removal.
- Creates a fresh database safety backup before applying repairs and records an evidence history row for each removed profile.

## Sanity gate

- Test, draft, submitted, rejected and unpublished events cannot change permanent profiles.
- Super Admin publication remains the only event-driven profile synchronization point.
- Approved participant results and point ledgers are never deleted or rewritten by the repair.
- Existing Special Category recovery remains untouched.

## Validation

- Full JavaScript regression suite.
- Focused publication-boundary and repair assertions.
- Repository diff and version/build checks.
- PHP/database runtime is unavailable in the Codex workspace; Staging preview and approval-flow verification are required before Production promotion.

## Migration status

- No migration wrapper. The repair evidence table is created additively when the Super Admin repair page is opened.

## Deployment status

- Candidate targets `develop` only.
- Production is blocked until Staging confirms the repair preview list and one complete unpublished-versus-published Salsa workflow.
