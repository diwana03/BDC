# BDC 2.3.3-dev272 — build 2978

## Independent judge-lock correction

- Fixes a defect where completing a later judge could make an earlier judge appear unlocked.
- Uses the latest canonical browser session independently for every judge.
- Submitted J1 remains locked when J2, J3, or any later judge submits.
- Newly submitted judges are added to the locked set; existing locks are never replaced.
- Final matrix overwrite protection now reads only canonical submitted sessions.
- Progress and all-submitted checks use the same canonical-session rule.

## Data integrity

- Adds a migration that removes obsolete duplicate browser sessions while retaining the latest session for each judge.
- Adds a database uniqueness guard preventing multiple browser-session rows for one judge.
- Older duplicate judge links become invalid after cleanup; the current latest judge link remains active.

## Parity Gate

- **Testing Score Dashboard:** canonical Test judge sessions, progress, all-submitted state, matrix locks, and submitted-score protection checked.
- **Live Scoring Dashboard:** canonical Live judge sessions, progress, all-submitted state, matrix locks, and submitted-score protection checked.
- **Projector:** no display behavior changed; projector reads judge marks rather than editable browser-session locks.
- Candidate/static validation: `git diff --check` and canonical-query parity checks passed.
- PHP runtime and migration execution remain pending Staging validation.
- Production promotion remains blocked until Staging confirms sequential judge submissions retain all previous locks.

## Deployment

- Database migration: `20260818_0315_canonical_judge_sessions.php`.
- Staging deployment: pending.
- Production deployment: not performed.
