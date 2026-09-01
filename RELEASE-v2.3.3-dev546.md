# BDC v2.3.3-dev546

## Fix

- Loads the complete Dance Cup Automatic scoring workspace through the new `dance-cup-automatic-workspace-v546.php` include.
- Bypasses the stale PHP opcode entry that continued returning an empty include after `v2.3.3-dev545` deployed the current source successfully.
- Retains the shared workspace mount marker, Judge Scoring section, matrix, ranking, approval and projection controls.

## Validation

- Production `v2.3.3-dev545`: installed version confirmed; scoring shell present; included workspace still returned only a newline with no mount marker.
- New versioned include is byte-identical to the current shared workspace before the page routing change.
- Focused Automatic Dance Cup workflow regression: passed.
- Candidate static suite: 151 JavaScript checks with the same 28 failures as the unchanged baseline.
- PHP runtime syntax: pending deployment because PHP is unavailable locally.
- Database migration: none.

## Parity Gate

- Testing Score Dashboard: uses the same versioned workspace include with isolated Test tables.
- Live Scoring Dashboard: uses the same versioned workspace include with Live tables.
- Projector: links and controls restored through the workspace; projector implementation is unchanged.
- Runtime: pending deployment and direct mount-marker verification.

## Deployment Status

- Candidate only. Not deployed to Staging or Production.
