# BDC 2.3.3-dev449 · build 3155

## Show default Dance Cup registration categories

- Displays every default style, entry type and level saved by the public Dance Cup registration form immediately after submission.
- Separates Registration Categories from Approved Reusable Profiles so pending requests are never shown as missing data.
- Shows request status, gender, partner/team details and pending BDC-ID assignment.
- Adds direct navigation to Dance Cup Approvals.
- Keeps scoring results/history separate and introduces no database change.

## Validation

- Added registration-category visibility and separation regression coverage.
- Complete JavaScript regression suite passed.
- Deploy to staging and verify with the registrations submitted today.
