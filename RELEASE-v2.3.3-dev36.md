# BDC v2.3.3-dev36

## Portal Branding & Environment Theme Update

- Updates the public BDC Portal homepage to the Bachata Dance Council red, black and white visual identity.
- Adds a clear Home button on the Portal navigation linking to https://bachatadancecouncil.com/ for both Production and Staging base paths.
- Keeps the existing Portal navigation, leaderboard, participant results, result repository, registration, profile update and Hall of Fame functionality unchanged.
- Adds environment-aware Admin Dashboard styling using the existing Release Manager environment detection.
- Production Admin uses the BDC red and black identity.
- Staging Admin uses a clearly different amber and gold identity to reduce environment confusion.

## Safety and Compatibility

- No database migration is required.
- No scoring, registration, result publication or points logic is changed.
- `config/config.php` is untouched.
- No Production or Staging deployment is included in this release.
