# BDC 2.3.3-dev233

## Automatic post-submit workflow recovery

- Restores Callback, Semifinal and Final controls after Automatic Submit Scores.
- Treats `tie_pending` as a completed calculation state that requires a Chief Judge decision.
- Shows tied competitors directly in both Test and Live Automatic dashboards.
- Keeps next-round controls locked until all callback-boundary ties are resolved.
- Shows Move Callbacks to Semifinal, Move Callbacks Directly to Final, or Semifinal to Final immediately after ties are cleared.
- Routes Test Automatic Save, Calculate, Submit, Tie Resolution and Next Round actions through the established Test scoring engine.
- Repairs the missing semicolon on the Test Automatic bootstrap import.
- Preserves existing marks, drafts, calculated results and dev230-dev232 fixes.

## Validation and deployment

- Test and Live Automatic workflow conditions checked for parity.
- Static PHP structure and release JSON validation completed.
- PHP runtime validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
