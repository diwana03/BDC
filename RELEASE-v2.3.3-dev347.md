# BDC 2.3.3-dev347

## Judge-scope clarity

- Displays `N/A` in Leader/Follower score matrices when the judge is not assigned to that role.
- Retains `—` only for an applicable judge whose mark has not been received.
- Preserves `YES`, `A1`, `A2`, `A3`, score totals, tiering and calculation behavior unchanged.
- Applies the same shared renderer to isolated Test data and Live scoring data.

## Parity Gate

- Candidate/static: isolated Test matrix routing through `bdc_test_scoring_judges` and `bdc_test_scoring_marks` checked in `live-display/feed.php`.
- Candidate/static: Live matrix routing through `bdc_scoring_judges` and `bdc_scoring_marks` checked in the same shared file.
- Candidate/static: projector scope rendering checked for Leader, Follower and All judge assignments; Final couple matrices remain unchanged.
- Runtime: blocked until this exact `develop` commit is deployed to Staging and checked with Leader-only, Follower-only and All judges.
- Production: not deployed or approved.

## Migration

- No database migration. Existing judge `scoring_scope` values are read without modification.
