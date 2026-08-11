# BDC 2.3.3-dev9

## Fixes

- Restores the Registration Desk card as a full-width dashboard section instead of squeezing it into the page heading on mobile.
- Repairs invalid approval-modal markup that placed the footer and publish action outside the modal.
- Keeps the approval modal within the mobile viewport, makes its content scrollable, and keeps the publish action fully accessible.
- Restores the approval form submission so the existing atomic publishing workflow can add points, archive Heats, Final and Points results, mark the competition published, and expose Super Admin rollback.
- Preserves transaction rollback on any publishing failure, preventing partial point or repository updates.
- Applies the same modal repair to the isolated Scoring Tests workflow.

## Scope

No scoring formulas, points rules, database schema, repository storage rules, or deployment logic were changed.
