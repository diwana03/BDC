# BDC 2.3.3-dev365

## Role-specific Heats sheet

- Makes the direct-Final rule visible on Test and Live manual Heats sheets.
- If Followers are within the tier callback quota and Leaders exceed it, every Follower advances directly to Final and only Leaders appear for Heats scoring.
- Applies identically in reverse when Leaders are within quota and Followers exceed it.
- Applies to Tier 1 quota 5, Tier 2 quota 10 and Tier 3 quota 15.
- If both roles are within quota, the `dev364` **Go Directly to Final** action remains the only scoring action.
- Preserves the shared scoring engine, weights, alternates, ties, Chief Judge logic and callback transfer.

## Confirmed boundaries

- 8 Leaders / 7 or 6 Followers: both roles run Tier 1 Heats.
- 8 Leaders / 5 Followers: Followers direct to Final; Leaders run Heats.
- 20 Leaders / 10 Followers: Followers direct to Final; Leaders run Tier 2 Heats.
- 35 Leaders / 15 Followers: Followers direct to Final; Leaders run Tier 3 Heats.

## Parity Gate

### Candidate/static validation

- Testing: `admin/scoring-tests/index.php` updated first.
- Live: `admin/scoring/core.php` mirrors the same prompt and row exclusion.
- Automatic judge screens already exclude direct roles through `RoleAdvancementService` from `dev363`.
- Projector callback and Final data contracts are unchanged.
- Added `tests/role-specific-heats-sheet-v365.php` and expanded `tests/role-advancement-v363.php` for Tier 2 and Tier 3 mixed-role cases.
- PHP executable validation is unavailable in this Workspace runtime; Staging runtime validation remains mandatory.

### Staging/runtime validation

- Validate 8/5, 5/8, 20/10, 10/20, 35/15, 15/35 and 5/5.
- Confirm the direct role is absent from Heats entry, present in Final transfer and visible on Final projection.
- Production promotion remains blocked until Staging passes.

## Migration

- No database or configuration migration.
