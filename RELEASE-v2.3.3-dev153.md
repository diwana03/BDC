# BDC v2.3.3-dev153

Build 2483 · Development release · 13 August 2026

## Production migration compatibility

- Adds compatibility for migration `20260806_1700`.
- Allows only its two legitimate checksums found in repository history: the original applied version and the current dev146 dependency state.
- Retains the exact compatibility fix for migration `20260803_2200` from dev152.
- Keeps all unknown migration checksums blocked.
- Does not edit Production migration-history rows or application data.

## Deployment scope

- Development branch release for deployment through the web Release Manager.
- No direct Production deployment or Production configuration change.
