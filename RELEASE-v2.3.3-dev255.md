# BDC 2.3.3-dev255

## Flexible Final ranking depth

- Final scoring boards can select a required ranking depth from Top 3 through Top 20, capped by the number of confirmed couples.
- Live and Test Final scoring now accept exactly the selected Top-N ranks and leave all other couples unranked.
- Automatic judge progress, generated Test marks, and Relative Placement calculation use the same selected depth.
- Judge screens prioritise leader/follower bib numbers, warn immediately when a rank is reused, and include a private device-only draft-ranking scratchpad.
- Heats and Semifinal scoring behavior is unchanged.

## Verification

- Static whitespace/error check with `git diff --check`.
- Live/Test parity review across Final settings, score persistence, automatic judge progress, and Relative Placement inputs.
- PHP runtime was unavailable in the workspace; server-side runtime tests were not executed here.

## Parity Gate

- **Testing Score Dashboard:** `admin/scoring-tests/index.php` and `app/Services/TestAutomaticJudgeService.php` checked for setup, draft entry, automatic generation, validation, calculation and submission parity.
- **Live Scoring Dashboard:** `admin/scoring/core.php`, `judge-scoring/index.php`, `app/Services/AutomaticJudgeBrowserService.php` and `app/Services/RelativePlacementCalculator.php` checked for the corresponding Live workflow.
- **Live Scoreboard / projector:** no projector payload or rendering contract changes are required; only the number of published Final placements changes, and the existing Final result consumers accept that subset.
- **Candidate/static gate:** passed `git diff --check`; a focused Top-N Relative Placement regression case was added. PHP runtime execution was unavailable.
- **Staging/runtime gate:** pending deployment of this exact `develop` candidate by the user. Production promotion remains blocked until Staging verification passes.

## Migration and deployment

- Database migration: none; the existing Final-round `callback_count` stores the selected ranking depth.
- Deployment: source candidate only. No Production changes were made.
