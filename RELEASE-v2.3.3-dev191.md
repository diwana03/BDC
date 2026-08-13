# BDC 2.3.3-dev191

## Testing and Live dashboard parity

- Aligns the Testing scoring workflow visually with the Live scoring workflow.
- Keeps the normal Settings, Judge Setup, Judge Live Scoring, Competitor Progress, and Round Completion areas clean and consistent.
- Removes Testing reset controls from the normal judge setup and live-progress headers.

## Testing-only tools

- Groups random competitor, random judge, random score, and automatic-score generators inside a yellow **TEST TOOLS** panel.
- Moves destructive Testing reset controls into the same clearly marked panel.
- Keeps all generators and Testing reset actions completely absent from Live.
- Retains the existing isolated `bdc_test_*` data path; no Testing action writes to Live scoring data.

## Validation

- Testing was updated first and compared against the corresponding Live layout.
- Repository whitespace checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev191 to Staging through Release Manager.
- Production was not deployed or modified.
