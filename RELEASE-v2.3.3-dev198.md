# BDC 2.3.3-dev198

## Round-aware Testing tools

- Final rounds now ask for the number of finalist couples instead of exposing separate Leader and Follower counts.
- Generating finalists creates equal Leader and Follower pools required by the existing Final pairing workflow.
- Final rounds now show one Final judge count because all Final judges rank every couple using Relative Placement.
- Leader, Follower and shared-panel judge controls remain available for Heats and Semi-Finals.
- Random score generation and destructive reset controls remain Testing-only.

## Validation

- Repository whitespace checks passed.
- Final controls continue to submit through the established isolated Test services and `bdc_test_*` tables.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev198 to Staging through Release Manager.
- Production was not deployed or modified.
