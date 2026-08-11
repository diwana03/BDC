# BDC 2.3.3-dev3

Development release for Staging validation. Production is not part of this release.

## Registration Desk

- Adds BDC competitor search for Leaders and Followers.
- Shows division eligibility before a competitor can be selected.
- Revalidates the exact role and competitor record on the server.
- Blocks known ineligible BDC competitors from provisional override.
- Allows a genuinely missing or not-yet-imported dancer to be added provisionally with a mandatory audited reason.
- Assigns the bib while adding the competitor and rejects duplicate active bibs for the same role.
- Writes directly to the scoring-round entries used by the backend and live desk panels.

## Database secrets

- Removes database passwords from PHP configuration examples and newly generated configuration.
- Loads the application database password from `BDC_DB_PASSWORD` or an owner-only password file outside the application directory.
- Loads the Production read-only sync password from `BDC_PRODUCTION_READONLY_DB_PASSWORD` or its protected password file.
- Keeps passwords out of Git, application logs and process command arguments.
- Updates the installer to create the protected password file with mode `0600`.

## Staging deployment requirement

Before deploying this release, configure either `BDC_DB_PASSWORD` in the Staging server environment or `database.password_file` in protected Staging configuration. The referenced file must be outside the public application directory and readable only by the application account.

For Production-to-Staging database refresh, configure the separate read-only secret through `BDC_PRODUCTION_READONLY_DB_PASSWORD` or `staging_database_sync.production_readonly_database.password_file` on Staging only.

