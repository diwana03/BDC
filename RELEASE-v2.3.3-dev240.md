# BDC 2.3.3-dev240

## Official callback tier enforcement

- Makes the selected BDC tier authoritative for both YES and callback quotas.
- Tier 1 uses 5 callbacks per role, Tier 2 uses 10 and Tier 3 uses 15.
- Automatically repairs stale or invalid callback values such as 7.
- Normal Heats and Semifinals always derive the tier from the larger active role count.
- A smaller role advances up to the shared quota, capped by the competitors available in that role.
- Uses the larger Leader or Follower count for automatic tier selection.
- Applies through the shared Test and Live Heats/Semifinal calculation service.
- Leaves Special Category configuration separate.

## Validation and deployment

- Added regression checks for stale callback 7 and automatic role-count tiering.
- Static Test/Live calculation and display-path checks completed.
- Recalculate the affected round on Staging to refresh existing provisional results.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
