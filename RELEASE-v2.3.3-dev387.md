# BDC v2.3.3-dev387

## Complete Salsa publication review restored

- Restores the comprehensive pre-publication review for Salsa Rising and Salsa Open instead of showing only the progression-points table.
- Links the scorer or approver directly to the complete Heats scores/callbacks and the Final judge rankings/Relative Placement report.
- Keeps the authoritative fixed 5 / 4 / 3 / 2 / 1 Salsa progression points visible in the same approval workflow.
- Shows publication status and the previous rejection reason so an amended submission can be reviewed in context.
- Requires the scorer to acknowledge the Heats, Final and points review before requesting Super Admin approval.
- Restores a direct Back to Final Dashboard action.
- Prevents repeated report table headers, rows, ranking cards and Relative Placement summary cards from splitting incorrectly across printed/PDF pages.
- Does not change marks, Relative Placement calculation, fixed points, approval permissions, ledger writes or published archive generation.
- No database migration. Production untouched pending Staging validation.

## Validation

- PASS: active Salsa publish gate still routes Salsa Rising/Open Finals to the special publication workflow.
- PASS: Heats review handles both a matching Heats round and Direct-to-Final events.
- PASS: Final review opens the existing full judge-ranking and Relative Placement report.
- PASS: fixed point calculation and submit/approve/reject action paths remain present and unchanged.
- PASS: executable static regression test covers the active publish-gate integration and print rules.
- NOT RUNTIME-TESTED: PHP CLI is unavailable in this workspace.
- NOT RUNTIME-TESTED: browser, database writes and PDF rendering require the exact commit on Staging.

## Parity Gate

- Testing Score Dashboard: N/A — publication approval and repository ledger writes intentionally exist only in the Live real-data workflow; no isolated test publication endpoint exists.
- Live Scoring Dashboard: candidate/static PASS for the Salsa special approval route, review links, status/rejection context and unchanged approval actions.
- Live Scoreboard/projector: N/A — this repair changes an admin approval/report surface and does not change projected data or commands.
- Reports/PDF: candidate/static PASS for repeatable table headers and page-break protection; Staging print/PDF visual verification remains required.
- Production promotion is blocked until Staging validates the complete Salsa Heats → Final → points review, reject/resubmit, approval and PDF workflow.
