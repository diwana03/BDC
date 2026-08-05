# BDC 2.3.3-dev4

Result repository files are no longer written inside the deployed application tree.

- Production and Staging require separate `results.storage_path` directories outside `portal` and `BDC_STAGING`.
- HTML, PDF and CSV files are served through a controlled endpoint only when backed by a published repository record.
- New uploads, scoring archives and temporary HTML publication files use protected external storage.
- `bin/migrate-result-files.php` safely moves existing local repository files and updates their database records.
- Deployments cannot overwrite or delete result files.
