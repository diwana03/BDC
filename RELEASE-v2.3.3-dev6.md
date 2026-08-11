# BDC 2.3.3-dev6

## Production backup compatibility

- Replaces the Bluehost-incompatible inline `php -r` database backup command with a script-based runner.
- Runs the backup utility from the installed Staging release while explicitly loading the Production application configuration.
- Retains the Production database backup with the release file backup before deployment begins.
- Leaves Production unchanged when the mandatory backup cannot be created or retained.
