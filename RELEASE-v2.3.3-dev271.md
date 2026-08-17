# BDC 2.3.3-dev271 — build 2977

## Clear Final placement status

- A ranked couple now receives a green-tinted card, strong green side rail, and large **PLACED #N** badge.
- An unranked couple displays a red **NO RANK** badge with a subtle red card state.
- The sticky toolbar shows **Placed N of Top N** and the exact missing placement numbers.
- The selected rank retains its check mark and highlighted button.
- Moving a used placement remains flexible and requires confirmation before the rank is reassigned.
- The existing save notification and final submission lock remain unchanged.

## Testing-first implementation

- Added the missing isolated Test Final judge screen using `bdc_test_*` tables.
- The Test screen now mirrors the Live card status, progress, missing ranks, rank reassignment, autosave, completion validation, and submission lock.
- Live implementation remains in `judge-scoring/index.php` and uses production scoring tables.

## Parity Gate

- **Testing Score Dashboard:** `test-judge-scoring/index.php` and `test-judge-scoring/final.php` checked with isolated Test data paths.
- **Live Scoring Dashboard:** `judge-scoring/index.php` checked for Final draft save, reassignment, completion, submission, and locked state.
- **Projector:** no projected data, score, result, finalist, or display behavior changed; existing projector files remain unaffected.
- Candidate/static validation: `git diff --check` passed; Test and Live labels/states were statically compared.
- PHP runtime lint is unavailable in this workspace and remains pending on Staging.
- Staging runtime workflow validation remains pending deployment through Release Manager.
- Production promotion is blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Staging deployment: pending.
- Production deployment: not performed.
