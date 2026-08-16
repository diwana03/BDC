# BDC 2.3.3-dev235

## Leader and Follower split projector

- Replaces the combined Competitors grid with two equal panels.
- Shows Leaders on the left and Followers on the right simultaneously.
- Gives each role its own heading, grid, photos, bibs, flags and country labels.
- Slices each role independently so one role cannot push the other role onto a later page.
- Synchronizes projector paging using the role with the most competitors.
- Updates automatic page-count and advance calculations to match the split layout.
- Applies through the shared Test and Live projector feed.
- Preserves dev230-dev234 scoring, callback and time-selection fixes.

## Validation and deployment

- Shared Test and Live projector path checked.
- Split-role pagination calculations checked statically.
- PHP runtime and full-screen visual validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
