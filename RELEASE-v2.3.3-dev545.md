# BDC v2.3.3-dev545

## Fix

- Forces `app/Views/admin/dance-cup-automatic-workspace.php` into the deployment payload after Production `v2.3.3-dev544` proved the page wrapper rendered but the included workspace did not.
- Adds a nonvisual `data-dc-workspace-mounted` marker at the first rendered line of the shared workspace.
- Preserves the complete shared Test and Live Judge Scoring, score matrix, ranking, approval and projection workspace.

## Validation

- Production `v2.3.3-dev544` baseline: `automatic-scoring-shell` present; `automatic-workspace` and `judge-progress` absent; shell empty at the include boundary.
- Focused Automatic Dance Cup workflow regression: passed.
- Candidate static suite: 151 JavaScript checks with the same 28 failures as the unchanged baseline.
- PHP runtime syntax: pending Staging because PHP is unavailable locally.
- Database migration: none.

## Parity Gate

- Testing Score Dashboard: shared workspace file included.
- Live Scoring Dashboard: same shared workspace file included.
- Projector: links restored through the shared workspace; projector implementation is unchanged.
- Staging runtime: pending deployment of this exact candidate.
- Production promotion: blocked until the workspace marker and roster transition render successfully.

## Deployment Status

- Candidate only. Not deployed to Staging or Production.
