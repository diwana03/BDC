# BDC 2.3.6 dev738

## Finalist injury withdrawal repair

- Fixes `SQLSTATE[HY093]: Invalid parameter number` when an authorized operator removes an injured competitor from a Jack & Jill Final.
- Uses separate native SQL bindings for the finalist's Leader and Follower pair lookup.
- Applies the same correction to the isolated Testing Score Dashboard and the Live Scoring Dashboard.
- Keeps the previous-round result unchanged and withdraws the competitor from the Final only.
- Removes marks and calculated results only for the affected Final pair, matching the existing protected workflow.

## Parity Gate

### Candidate and static validation

- Testing Score Dashboard: `admin/scoring-tests/index.php` Finalist removal path checked.
- Live Scoring Dashboard: `admin/scoring/core.php` Finalist removal path checked.
- Live Scoreboard and projector: no renderer, projection state, command, reveal, score calculation or audience asset changed.
- Focused regression: `tests/finalist-removal-bindings-v738.js`.
- Focused Finalist removal regression passed.
- Full JavaScript suite: 269 passed, 0 failed.
- Two PHP-dependent wrapper checks were not runnable because PHP is unavailable in this workspace; neither targets the changed Finalist-removal path.

### Staging and runtime validation

- Not runtime-tested on Staging yet.
- Production promotion remains blocked until the exact `develop` commit is deployed to Staging and Finalist removal is verified there.

## Migration and deployment

- Database migration: none.
- Deployment status: workspace candidate only. Not pushed and not deployed.
