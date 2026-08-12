# BDC v2.3.3-dev140

## Test Automated Scoring workflow repair

- Shows the live Competitor Scoring Progress matrix on the main Test Automated Scoring page.
- Adds a transactional Generate & Submit All Judges action.
- Generates valid scope-aware Heats marks or unique Final ranks for every Test judge.
- Marks every successfully generated judge Submitted with 100% progress and timestamps.
- Adds reusable Test session completion checks.
- Leaves the official Live scoring engine and official points unchanged.

## Verification

- PHP syntax validation for every changed PHP file.
- Existing automatic scoring engine regression test.
- Existing Relative Placement and security smoke tests.
