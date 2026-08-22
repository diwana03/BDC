# Scoring Surface Parity Baseline

This file defines the release baseline shared by the Testing Score Dashboard, Live Scoring Dashboard, and public Live Scoreboard/projector.

## Required workflow chain

| Capability | Testing | Live | Projector impact |
|---|---|---|---|
| Create and edit an unscored round | Required | Required | Event, division, round and schedule labels |
| Add/remove competitors and update bibs | Required | Required | Competitor and finalist screens |
| Preserve Leader/Follower role identity | Required | Required | Separate Heats score panels and paired Finals |
| Add judges, scopes and Chief Judge | Required | Required | Judges and scoring-progress screens |
| Generate 12-hour judge links | Required | Required | Judge progress refresh |
| Accept criteria and save drafts | Required | Required | Scoring progress refresh |
| Calculate and sort | Required | Required | Live contestant scores |
| Enable Submit only when all assigned judging is complete | Required | Required | Scoring-completed state |
| Submit and lock scores | Required | Required | Callback/finalist/result screens |
| Reopen submitted judging with a reason | Required | Required | Progress returns to active state |
| Resolve callback ties | Required | Required | Callback/finalist screens |
| Create Semifinal or Final from callbacks | Required | Required | New round becomes selectable |
| Pair Final couples and calculate Relative Placement | Required | Required | Final couples, full results and podium |
| Preview/print drafts and final results | Required | Required | Full-score screens use the same saved data |
| Missing-photo fallback | Same cute-animal set | Same cute-animal set | Rabbit, baby elephant or panda, never a blank slot |
| Country display | One flag per contestant | One flag per contestant | Same-country couples still show two flags |
| Projector screen style | Same four premium presets | Same four premium presets | One session choice applies to every audience screen |
| Projector music | Same isolated upload/player controls | Same isolated upload/player controls | Loops in the persistent projector shell; effect sounds remain independent |

## Intentionally Test-only

- Random competitor, judge and score generators.
- Test reset and isolated `bdc_test_*` cleanup controls.
- Simulation and fixture tools that never write to Live tables.

No business workflow, scoring action, label, validation rule, calculation, print action, or projector state may be Test-only or Live-only.

## Release gate

Run:

```sh
php tests/scoring-surface-parity.php
php tests/automatic-scoring-v235.php
php tests/relative-placement-v215.php
php tests/security-smoke.php
```

Any local/static failure blocks the candidate push. After the complete candidate is pushed to `develop`, the user deploys that exact commit to Staging and the browser workflow is checked there. Any Staging/runtime failure blocks Production approval, and the user must be told exactly which surface failed.
