# BDC 2.3.3-dev23

Build 2353, 6 August 2026

## Included

- Automatically links dual-role competitor profiles when exact reliable identifiers match.
- Sends uncertain single-identifier matches to a Super Admin review queue; names alone never link profiles.
- Keeps both BDC IDs, role-specific divisions, results and points while All Divisions uses the shared career identity.
- Adds an Admin photo-framing tool with drag, zoom and preview; saves a derived frame image and preserves the original.
- Requires email verification after password login for Super Admin and Admin/Scorer-capable accounts.
- Adds a 30-day Remember this computer option using hashed, expiring device tokens.
- Enables Automated Scoring Phase 1: create/select an event with the manual setup options and continue to a separate setup-only Automated Heats screen.

## Safety

- New database objects are delivered through immutable migration `20260806_2300`; existing shared migrations are unchanged.
- Automated Heats does not calculate, publish or change scores in this phase.
- No Production or Staging data is modified by the source release.
