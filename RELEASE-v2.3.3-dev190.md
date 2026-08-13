# BDC 2.3.3-dev190

## Testing special-category correction

- Fixes the HTTP 500 returned after selecting **Save & Lock YES Count** on the Testing Scoreboard.
- Keeps the special-category tier form inside the active Testing dashboard/workspace request instead of routing through the legacy standalone handler.
- Saves 5, 10, or 15 YES consistently to both `yes_count` and `callback_count`.
- Keeps the official YES and alternate weights locked.
- Supports unlocking the YES tier before judging starts.
- Prevents tier changes after any judge mark has been saved.
- Reloads the same Testing round immediately so the displayed YES count matches the selected tier.

## Parity and validation

- Live already uses its authorised scoring settings workflow and retains equivalent validation and locked weights.
- `git diff --check` and JavaScript syntax validation passed.
- PHP CLI is unavailable locally; Staging migration and health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev190 to Staging through Release Manager.
- Production was not deployed or modified.
