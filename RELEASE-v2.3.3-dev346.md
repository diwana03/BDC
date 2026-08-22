# BDC 2.3.3-dev346

## Scoring progress projection

- Replaces the anonymous judge-submission counter with a premium live status board.
- Shows submitted judges on the left and pending judges on the right, with judge order and Chief Judge identity preserved.
- Retains the central submitted/total count and adds a clear completion bar.
- After all judges submit, shows real elapsed scoring times from first open to submission, celebrates the fastest judge as the Speed Star and gives the slowest judge a friendly Scenic Route Award.
- Uses the existing responsive projector stage across HD, 4K, ultrawide, 4:3 and portrait outputs.

## Parity Gate

- Candidate/static: shared Test and Live projector feed checked in `live-display/feed.php`.
- Candidate/static: shared responsive projection CSS compatibility checked in `public/css/projector-responsive-v344.css`.
- Candidate/static: Test tables remain isolated through the existing `bdc_test_*` table routing; Live continues using real scoring tables.
- Runtime: blocked until this exact `develop` commit is deployed to Staging and checked with judges in not-started, scoring and submitted states.
- Production: not deployed or approved.

## Migration

- No database migration. Existing judge assignments and session statuses are read without modification.
