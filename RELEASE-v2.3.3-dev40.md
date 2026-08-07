# BDC v2.3.3-dev40

## Environment-Isolated Result Repository

- Production always resolves BDC-managed result documents from the protected Production repository only.
- Staging always resolves BDC-managed result documents from the protected Staging repository only.
- Production and Staging repository roots are required to be different and environment-specific.
- Legacy result paths and old BDC portal URLs are used only to recover the historical filename; they are never served as a cross-environment fallback.
- Historical Heats, Final and Points records can resolve by filename from the current environment repository after repository synchronization.
- The protected result-file endpoint now authorizes legacy BDC-managed records by filename while still serving bytes only from the current environment repository.
- Genuine third-party external document URLs remain supported.

## Safety

- No Production data is changed.
- No Staging or Production deployment is performed by this release commit.
- No database migration is required.
- Scoring, competitor identity and point progression logic are unchanged.
- `config/config.php` is untouched.
