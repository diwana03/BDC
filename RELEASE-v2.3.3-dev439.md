# BDC 2.3.3-dev439

## Outcome

- Adds Dance Cup Participant Management for approved reusable profiles, profile filters, partner/team details, and approved career totals.
- Changes Dance Cup finalization to two stages: the scoring operator submits and locks; only a Super Admin can approve and publish Live results to permanent Dance Cup history.
- Keeps Test results isolated and explicitly blocks Test publication.
- Makes the existing automatic judge draft saving visible on the scoring screen.
- Keeps Jack & Jill Automatic event creation reachable while an existing round is open.
- Adds Profile Request counts to every status and a direct All-results link when the selected status is empty.

## Database

- New additive migration `20260827_0400_dance_cup_result_approval`.
- Adds submission and approval metadata to Test and Live Dance Cup competition tables.
- Adds the Live-only `bdc_dance_cup_result_history` table. No existing rows or tables are deleted or rewritten.

## Parity Gate

- Test Dance Cup dashboard: submission locks the round, but permanent publication is rejected.
- Live Dance Cup dashboard: submission creates `pending_approval`; Super Admin approval creates idempotent history rows and changes status to `approved`.
- Judge scoring: slider changes continue to auto-save after 900 ms and now show that protection.
- Projection: no payload or display command changed; pending and approved rounds retain their calculated results.
- Jack & Jill Live Automatic: existing event creation engine is retained and the create-another route is visible from an open round.

## Validation

- Focused regression: `node tests/dance-cup-approval-dashboard-autosave-v439.js`.
- Complete JavaScript regression suite.
- `git diff --check`.
- PHP CLI is unavailable in this workspace, so PHP runtime and migration execution are **Not Runtime-Tested**.
- Staging must deploy this exact candidate and validate the migration, Test submission block, Live approval, autosave, dashboard filters, Profile Request status counts, and Jack & Jill event creation before Production promotion.
