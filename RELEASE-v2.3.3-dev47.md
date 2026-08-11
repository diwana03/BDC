# BDC v2.3.3-dev47

## Automatic Scoring V1 — Judge Browser Workflow

- Keeps the existing organiser flow: Event → Category → Automatic Scoring → Heats / Final.
- Keeps the existing Registration Desk for competitors and bib numbers.
- Adds secure per-judge browser scoring links on the same Automatic Scoring dashboard.
- Judges do not need an admin login; each link is tied to one round and one judge through a random token stored only as a hash.
- Heats and Semi-Finals use numeric 0–100 judge scores with autosave and quick ±0.5 / ±1 controls.
- Finals use the existing Relative Placement methodology: each judge ranks every confirmed couple once.
- Organisers see live per-judge progress: Not Started, Scoring, Submitted, percentage complete and last activity.
- Judge Submit validates completeness and permanently locks that judge scorecard.
- Super Admin can unlock a submitted judge only by entering a reason; the action is recorded in the scoring audit log.
- Link regeneration invalidates the previous judge link.
- Existing scoring calculations and Relative Placement calculation code remain unchanged.
- Existing Registration Desk workflow remains unchanged.
- Existing BDC ID assignment and identity matching remain unchanged.
- Existing standard and special-category point logic remains unchanged.
- Release Manager code and release-state chain are not modified in this release.

## Database

Adds `bdc_scoring_judge_sessions` for secure judge tokens, progress state, submission lock state and unlock audit metadata.
