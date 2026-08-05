# BDC 2.3.3-dev2

## Fixed

- The Production-to-Staging schedule Save button now works even before database sync credentials are configured.
- Live Sync Now is no longer silently disabled when configuration is incomplete.
- Sync setup errors and successful actions are shown inside the Production Data to Staging panel.
- Save and Live Sync buttons show immediate progress text after submission.

## Safety

- Production remains a read-only source.
- Staging remains the only writable target.
- Runtime, identical-database, backup, restore, deployment and scoring locks are unchanged.
