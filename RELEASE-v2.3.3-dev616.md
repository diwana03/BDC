# BDC 2.3.3-dev616

## Projection presentation update

- Adds a 10% top and bottom safe area to the shared Test/Live Jack & Jill projector and the separate Dance Cup projector.
- Adds a click-to-enter fullscreen control to both public projector entry points. Browsers require this direct user action; Escape exits fullscreen.
- Displays internal `rising` division keys publicly as `Intermediate`, including Bachata and Salsa, while leaving stored keys, eligibility, scoring and progression unchanged.
- Preserves the dev615 competitor and judge card design, polling, projector tokens, judge links, scoring data and reveal controls.

## Validation

- Candidate/static: focused projector integration test, JavaScript parse checks, JSON parse and `git diff --check`.
- Migration: none.
- Deployment: source candidate only; the user deploys through Release Manager.
- Runtime: not tested on Staging. Production promotion remains blocked until the exact candidate is deployed and the Jack & Jill Test/Live and Dance Cup projector screens are checked.

## Parity gate

- Test dashboard/projector: shared `live-display/index.php` and `live-display/feed.php` paths checked statically.
- Live dashboard/projector: the same shared paths checked statically.
- Dance Cup projector: separate `admin/dance-cup/projector.php` checked statically.
- Staging/runtime: pending.
