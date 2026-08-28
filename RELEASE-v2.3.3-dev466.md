# BDC 2.3.3-dev466

## Dance Cup judging panel repair

- Fixes HTTP 500 on multi-category panel detail pages such as panel 1.
- Replaces the MySQL-sensitive correlated judge-progress query with a simple judge list and one compatible submitted-category count per judge.
- Wraps all panel GET-time reads so an unexpected database issue is shown to the administrator instead of crashing the page.
- Preserves panel membership, judges, judge links, Chief ordering, marks and submissions.

## Included

- Includes the dev465 independent Dance Cup registration-status correction.
