# BDC v2.3.3-dev541

## Profile integration API hotfix

- Fixes the authenticated API service parse failure caused by invalid arrow-function closure syntax.
- Adds a regression check preventing `fn (...) use (...)` syntax from returning.
- Keeps the API queue-only: no points, scoring, result or leaderboard writes.
- No database migration.

## Verification

- Profile integration API checks passed.
- Integration credential checks passed.
- JavaScript syntax and Git diff checks passed.
- Production authentication handshake must be repeated after deployment.
