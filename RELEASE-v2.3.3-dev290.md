# BDC 2.3.3-dev290 — Build 2996

## Critical Final scoring integrity hotfix

- Final progress counts only marks connected to the current confirmed pair set and current round judges.
- Stale marks belonging to removed or replaced Final pairs are removed automatically.
- Stale marks belonging to removed or replaced judge records are removed automatically.
- Affected judge sessions show their true completion count and remain open for valid resubmission.
- Test and Live use the same validation and recovery logic.

## Validation

- Repository whitespace and patch validation passed.
- PHP CLI is unavailable in the development workspace; Release Manager staging deployment must perform PHP runtime validation.
