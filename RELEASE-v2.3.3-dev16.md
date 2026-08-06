# BDC 2.3.3-dev16

## Point adjustment approval workflow

- Scorers can select a contestant and event, view existing points by division and role, and request additional missing points.
- Requests never change official points before Super Admin approval.
- Approval atomically appends the point transaction and participant event history; rejection and every decision remain audited.
- Pending point adjustments and competition publications are prominent on the Admin dashboard.

## Duplicate protection

- Event Duplicate Finder flags multiple point transactions for the same participant, event, division and role.
- Exact repeated point and placement values are labelled probable duplicates; different values require manual review.
- Duplicate participant merging now requires Super Admin access and shows overlapping event points before merge.
- Confirmed duplicate transactions can be removed during the same atomic merge while legitimate appended points are preserved.

## Validation

- No existing point values or scoring formulas are modified by the migration.
- The new database table is additive and is created through the standard migration runner.
