# BDC 2.3.6 dev739

## Protected injured-Finalist recovery

- Adds one authorized recovery action for a scoring-locked Salsa or Bachata Jack & Jill Final.
- Requires Scorer, Master Scorer or Super Admin access, a written reason and exact `WITHDRAW FINALIST` confirmation.
- Creates an automatic checkpoint that now includes the full Final roster as well as judges, marks, results, pairings and judge sessions.
- Withdraws only the selected injured finalist from the current Final and optionally promotes the next-ranked competitor of the same role.
- Clears only the current Final's marks, calculated placements and pairings, then reopens all Final judge sessions for a clean rematch.
- Revokes the old Emcee matching link, resets the Final to Draft, locks result reveal and returns the event projector to Holding.
- Leaves Heats/Semifinal entries and results unchanged.
- Refuses direct use on an unlocked, pending-approval, published or archived Final.

## Parity Gate

### Candidate and static validation

- Testing Score Dashboard: protected recovery form, authorization, confirmation and isolated Test tables checked.
- Live Scoring Dashboard: identical protected recovery workflow and Live tables checked.
- Salsa and Bachata: the shared Final workflow uses the round's configured dance style without style-specific branching.
- Projector: recovery changes only the affected event session to Holding and locks result reveal.
- Backup restore: new snapshots include roster entries; older snapshots remain compatible because entries are restored only when present.
- Focused regression: `tests/injured-finalist-recovery-v739.js`.
- Focused injured-Finalist recovery and prior HY093 regressions passed.
- Full JavaScript suite: 270 passed, 0 failed.
- Two PHP-dependent wrapper checks were not runnable because PHP CLI is unavailable in this workspace; neither targets the changed Final recovery path.

### Staging and runtime validation

- Not runtime-tested on Staging yet.
- Production promotion remains blocked until this exact `develop` commit is deployed to Staging and the recovery is verified on an expendable Test Final.

## Migration and deployment

- Database migration: none.
- Deployment status: workspace candidate only. Not pushed and not deployed.
