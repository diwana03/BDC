# BDC 2.3.3-dev558

- Adds a Super Admin-only API Changes approval/rejection panel.
- Adds signed API action proposals for controlled competitor, judge and SDC updates/removals.
- No proposed action changes live data before approval.
- Approval locks and revalidates the target, applies atomically, and records the reviewer in the audit log.
- Rejects stale proposals, unsupported fields, novice SDC categories and raw SQL.
