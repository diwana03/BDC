# BDC 2.3.3-dev234

## Manual next-round time selection

- Replaces unreliable browser `datetime-local` controls with separate Date, Hour, Minute and AM/PM selectors.
- Provides five-minute choices from 00 through 55.
- Applies to Callback to Semifinal, Callback directly to Final and Semifinal to Final.
- Applies consistently across Test and Live Automatic and their underlying scoring dashboards.
- Validates and converts the manual 12-hour selection before saving.
- Saves `scheduled_at` when creating a new round and when reusing an existing child round.
- Preserves all existing scores, drafts, callback results and dev230-dev233 fixes.

## Validation and deployment

- Test and Live schedule fields and handlers checked for parity.
- Browser `datetime-local` removed from every next-round action.
- Static structure and release JSON validation completed.
- PHP runtime validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
