# BDC 2.3.3-dev362

## Manual scoring roster checkpoint

- Manual means the scorer enters the marks; roster safety and tier selection remain automatic.
- Adds **Save Competitors** and **Submit Competitors** to Live manual Heats and Semifinals.
- Submitting locks competitor additions, removals and bib changes.
- Manual mark saving, calculation and submission are rejected until the roster is submitted.
- Super Admin can reopen a roster only with a reason and an explicit caution confirmation; scoring locks again until resubmission.
- Automatic tiering uses the larger individual Leader or Follower count, never the combined count.

## Parity Gate

- Candidate/static: Test dashboard `admin/scoring-tests/index.php` checked for isolated checkpoint, lock, reopen, scoring gate and automatic tier behavior.
- Candidate/static: Live dashboard `admin/scoring/core.php` checked for the same real-data behavior through the shared `ScoringRosterCheckpointService`.
- Candidate/static: projector reviewed; no projected competitor, judge, progress or result payload changes are required for this admin-only checkpoint.
- Runtime: Staging browser validation remains required after the user deploys this exact `develop` candidate.
- Production: not deployed or promoted.

## Database and deployment

- Database migration: none. Reuses the existing additive roster checkpoint schema and service.
- Deploy `develop` to Staging through Release Manager, validate the manual Heats and Semifinal workflows, then decide Production promotion.
