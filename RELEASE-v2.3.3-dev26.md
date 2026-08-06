# BDC 2.3.3-dev26, build 2356

## Identity Match Undo patch

- Adds a visible **Undo Identity Match** action to linked identity profiles.
- Restricts identity reversal to Super Admin accounts and requires CSRF plus confirmation.
- Separates only the selected BDC profile and safely removes empty or unnecessary one-person identity groups.
- Preserves both competitor profiles, BDC IDs, roles, divisions, results, placements and points.
- Records the identity group, separated profile and before/after membership in the audit log.
- Recalculates All Divisions leaderboard grouping naturally from the remaining shared-person references.

Deploy to Staging first. Production is unchanged.
