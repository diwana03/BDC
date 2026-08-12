# BDC v2.3.3-dev152

Build 2482 · Development release · 13 August 2026

## Production deployment compatibility

- Adds the exact known composite checksum produced when dev146 extended `SchemaUpdater.php`.
- Allows databases that previously applied migration `20260803_2200` to continue deployment safely.
- Keeps checksum validation fail-closed for every unknown migration or dependency state.
- Does not edit, delete, or recreate any Production migration-history record.
- Requires no manual Production database change.

## Included changes

- Includes the public registration division grouping and profile-photo adjustment released in dev151.

## Deployment scope

- Development branch release for deployment through the web Release Manager.
- No direct Production deployment or Production configuration change.
