# BDC 2.3.3-dev450 · build 3156

## Emergency Jack and Jill scoring recovery

- Restores `admin/scoring/core.php` byte-for-byte from the last clean source immediately before public commit `40f3a94` corrupted it.
- Removes literal tool-output warnings and the `tokens truncated` placeholder embedded in the public PHP file.
- Restores the missing Jack and Jill roster, scoring, Final judge, Emcee matching, pairing sync, completed Heats report and scoring-entrypoint workflows.
- Adds checksum-backed regression coverage so a partial or truncated scoring core cannot pass the candidate gate again.
- Contains no Dance Cup projection consolidation, database migration or scoring-rule redesign.

## Parity Gate

- Testing Score Dashboard: shared `admin/scoring/core.php` test-data route and isolated workflow markers restored and covered by the full regression suite.
- Live Scoring Dashboard: the same complete shared entrypoint is restored for Live Manual and Automatic scoring.
- Live Scoreboard / projector: existing projector endpoints, commands and display files are unchanged; restored dashboard integration markers are regression-tested.

## Validation

- Restored file checksum matches the clean pre-corruption public parent and the validated local workspace copy.
- Complete JavaScript regression suite passed.
- Git diff and VERSION validation passed.
- PHP/browser runtime is not available locally and remains required on Staging.
- Production promotion is blocked until this exact commit passes Staging runtime validation.
