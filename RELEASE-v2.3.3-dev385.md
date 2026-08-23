# BDC v2.3.3-dev385

## Automatic Final live score synchronization

- Adds one authenticated no-cache Final score-state endpoint shared by isolated Testing and real Live data.
- Updates the open Automatic Final matrix every three seconds without refreshing the dashboard or moving the scorer's scroll position.
- Synchronizes judge marks, submitted locks, progress, Final ranks and calculated Relative Placement summaries.
- Refreshes the embedded judge-status panel only when judge session states change.
- Never overwrites a scorer input currently being edited.
- Manual Final scoring remains server-controlled and unchanged.
- Pairing, Emcee, scoring calculations, judge submission, projector and database schemas are unchanged.
- No database migration. Production untouched pending Staging validation.

## Parity Gate

- Candidate/static: Testing dashboard, Live dashboard, shared score-state endpoint and active footer asset integration checked.
- Runtime: Staging browser verification still required for judge draft, judge submit, scorer live update, full refresh persistence and projector parity.
