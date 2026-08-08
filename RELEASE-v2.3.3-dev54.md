# BDC v2.3.3-dev54

## Automatic Scoring Workflow Patch

- Restores Automatic Scoring to the same Registration Desk, competitor, bib and judge setup workflow used by the existing scoring engine.
- Removes the separate Automatic Setup/confirmation UI from the active workflow.
- Automatic-specific behavior is limited to secure judge browser links and live judge progress/scoring.
- The existing Automatic Judge Panel now starts cleanly with three blank rows when no judges are saved and supports unlimited additional judges with `+ Add Judge`.
- Saved judge rows are preserved.
- Judge cards provide Copy Link, WhatsApp, Email and Open actions.
- WhatsApp and Email actions prefill the event, category, round, judge name and secure scoring URL using the organiser's own client.
- Judge Submit & Lock, live progress and Super Admin audited unlock remain unchanged.
- Registration Desk, score calculation, Relative Placement, points and Release Manager workflow are unchanged.
