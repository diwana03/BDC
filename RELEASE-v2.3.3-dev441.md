# BDC 2.3.3-dev441

## Emergency repair

- Repairs Jack & Jill Manual and Automatic event creation with a clean dedicated endpoint.
- Preserves Bachata/Salsa, division, Heats/Final, schedule, existing-event and new-event fields.
- Preserves Registration Desk link creation and scoring audit evidence.
- Prevents the corrupted legacy discipline action file from being loaded during event creation.
- Restores the corrupted discipline action source used by later-round Jack & Jill workflows.
- Routes the legacy Dance Cup Automation URL into the complete styled Automatic workspace instead of rendering a duplicate partial page.
- Makes Dance Cup Participant Management open safely when approved-result history or approval columns are not yet migrated.
- Shows participant profiles with zero published-history totals until the migration completes.
- Does not change scoring calculations, marks, callbacks, rankings or existing database records.

## Validation

- Focused emergency regression test.
- Complete JavaScript regression suite.
- `git diff --check`.
- PHP CLI is unavailable in this workspace. Authenticated PHP runtime is **Not Runtime-Tested** and must be checked on Staging.
