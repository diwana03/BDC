# BDC 2.3.3-dev270 — build 2976

## Automatic Final placement protection

- Submitted judge columns now display a lock and are read-only in the Final scoring matrix.
- The server ignores attempted changes to placements belonging to submitted judge sessions, preventing HTML or request tampering.
- A Scorer, Master Scorer, or Super Admin can deliberately reopen one judge through the existing audited **RESUBMIT / Reopen Scoring** control and provide a required reason.
- Only the reopened judge column becomes editable; it locks again after resubmission.
- Manual Final entry remains available during its authorised draft workflow, while the completed-round lock continues to protect submitted results.

## Parity and verification

- Test and Live scoring dashboards use the same lock behavior and server enforcement.
- Judge submission and Relative Placement calculations remain unchanged.
- Projector behavior is unaffected.
- `git diff --check` passes.
- PHP runtime lint remains pending on Staging because PHP is unavailable in the local workspace.
- Staging runtime verification is pending deployment through Release Manager.
