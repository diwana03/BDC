# BDC 2.3.3-dev548 — Scoring Rigidity and Failure Recovery

Build 3254 · 2026-09-01

## Outcome

- Fixed automatic Dance Cup roster-to-scoring continuity already restored in dev547 and hardened the surrounding workflows.
- Renumbers active Dance Cup contestants and Jack & Jill Leaders/Followers contiguously after removal, using collision-safe transactional updates.
- Adds duplicate discovery and transactional merge protection for competitors and judges, including same-scope conflict blocking and audit history.
- Adds Judge Directory sorting, active/inactive filtering, safe deactivation, duplicate warnings, and Judge Merge access.
- Makes user-facing participant, competitor, judge, registration, result, event and scoring searches explicitly case-insensitive.
- Blocks progression on unresolved callback ties and gives the scoring desk copy, email and WhatsApp Chief Judge decision alerts.
- Preserves audited judge reopen controls and restores the missing isolated Test Final judge-save server path.
- Restores Test completed-Heats reports and Test Jack & Jill duplication access.

## Failure scenarios covered

- Submit/confirm roster with complete contestants and exactly one Chief Judge.
- Remove a contestant from the beginning, middle or end of a roster and reuse the next contiguous number.
- Reopen submitted judge scoring without deleting saved marks.
- Prevent duplicate competitor/judge identities from colliding in an active scoring scope.
- Detect case variants in search and duplicate discovery.
- Block callback progression until the Chief Judge resolves every exact-total tie.
- Keep Test and Live identity/scoring tables isolated during judge assignment and merge operations.

## Parity gate

- JavaScript/static regression suite: 152/152 passed.
- `git diff --check`: passed.
- Focused scoring rigidity, roster, tie, duplicate and workflow tests: passed.
- Local PHP CLI syntax gate: unavailable in this workspace because no PHP binary is installed.

## Runtime gate

- Staging runtime verification: not yet run for this commit.
- Production approval: blocked until the exact `develop` commit is deployed to Staging and Jack & Jill plus Dance Cup end-to-end/failure scenarios pass there.
