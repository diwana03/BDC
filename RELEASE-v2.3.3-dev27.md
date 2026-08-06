# BDC 2.3.3-dev27, build 2357

## Scoring Dashboard, Production recovery and country flags

- Restores `admin/scoring/index.php` as valid PHP source after the binary-file regression.
- Keeps completed and archived rounds off the active Scoring Dashboard.
- Retains the separate, role-protected Past Event Scores page and blocks direct URL access by regular Scorers.
- Shows verified Production rollback targets in Release Manager instead of hiding the recovery action when recent activity falls outside the short dashboard list.
- Creates a new full Production files-and-database backup before a selected rollback begins.
- Confirms the current and target versions and keeps rollback restricted to Super Admin.
- Bundles local SVG country flags and maps each participant country automatically.
- Displays the flag below the photo on Participant Results, the public career profile and the Admin competitor editor.

Push to `develop` only. Deploy to Staging manually through Release Manager. Production is unchanged.
