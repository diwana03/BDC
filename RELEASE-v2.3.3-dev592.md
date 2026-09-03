# Release v2.3.3-dev592

## Fix

- Correct the `stage_competitor_additions` adapter so its documented public fields are translated to the internal integration payload.
- Map `identity_code` to `council_id` and `bib_number` to `bib` before validation.
- Preserve deterministic batch keys and Super Admin approval; this release does not bypass review or write rosters directly.

## Verification

- Added a focused regression test for both public-to-internal field mappings.
- Re-ran the MCP roster, OAuth, discovery, admin-login and connector regression tests.
