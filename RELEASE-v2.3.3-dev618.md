# BDC 2.3.3-dev618

## Projection visibility and controls

- Shows the complete Leader and Follower totals with `PAGE X OF Y` in both roster headers as the projector advances.
- Adds a Full Screen control to the Jack & Jill Live Projection Control and the separate Dance Cup Projection Control.
- Keeps the audience projector's own Full Screen button because browsers require a click inside the window being enlarged.
- Expands the audience safe area to 10% top/bottom and 5% left/right for both Jack & Jill and Dance Cup projections.
- Preserves the dev617 15-per-role balanced pagination, approved card design, scoring, judge links, tokens and module separation.

## Validation

- Candidate/static: focused total/page, fullscreen-control and four-edge safe-area checks plus existing pagination, card-layout, feed-recovery, hot-path, fullscreen and label regressions.
- Migration: none.
- Deployment: source candidate only; the user deploys through Release Manager.
- Runtime: not tested on Staging. Production promotion remains blocked until the exact candidate is deployed and both control and audience screens are checked.

## Parity gate

- Test and Live Jack & Jill control/projector: shared paths checked statically.
- Dance Cup control/projector: separate paths checked statically.
- Staging/runtime: pending.
