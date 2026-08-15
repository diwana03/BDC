# BDC 2.3.3-dev199

## Random Match reliability

- Initializes the presenter-link and pairing-audit storage before Random Match runs.
- Removes the hidden dependency that required an administrator to open the Emcee Match Link page before using Random Match.
- Applies the repair to the shared pairing service used by both Testing and Live.
- Requires equal Leader and Follower finalist counts instead of silently creating incomplete couples.
- Reports the current Leader and Follower counts when matching cannot proceed.
- Keeps secure Fisher-Yates shuffling backed by `random_int()`.

## Validation

- Repository whitespace checks passed.
- The database initialization happens before the pairing transaction begins.
- No scoring, ranking or point-calculation rules were changed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev199 to Staging through Release Manager.
- Production was not deployed or modified.
