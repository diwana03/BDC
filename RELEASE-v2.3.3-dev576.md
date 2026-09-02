# v2.3.3-dev576

## Protected WDC Premium Preview

- Adds a separate Super Admin-only premium dashboard preview.
- Leaves the confirmed-working Dance Cup Competitor page unchanged.
- Uses the exact Production-proven WDC identity query; filtering, sorting, duplicates and completeness are calculated in PHP.
- Adds summary cards, case-insensitive search, entry type/style/profile filters, sortable columns, CSV export, Edit Profile and Adjust Photo actions.
- Adds a clearly marked `TEST` navigation entry so the preview can be runtime-tested before replacing the working page.

## Safety

- No migration and no data mutation.
- No BDC, SDC, scoring, result or championship-point change.
- The preview cannot replace the main WDC page until its Production/Staging runtime checks pass.

## Validation

- Full JavaScript regression suite: required before publishing.
- Preview open/search/filter/sort/export/mobile runtime checks: required after deployment.
