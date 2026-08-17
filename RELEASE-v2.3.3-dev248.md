# BDC 2.3.3-dev248

## BDC Chief Judge and tie rules

- Includes the Chief Judge mark in every Heats and Semifinal total as a normal panel score.
- Keeps the individual Chief mark visible for audit without counting it a second time.
- Groups all competitors with the same final total into one tie, regardless of the Chief's individual mark.
- Sends callback-boundary, alternate-boundary and alternate-order ties to an explicit Chief Judge decision.
- Calculates the exact remaining callback quantity from the complete unsplit tie group.
- Preserves independent Leader and Follower callback quantities and A1–A3 uniqueness.
- Updates Test and Live, Manual and Automatic calculation paths together.

## Example corrected

- With six confirmed callbacks and seven competitors tied on 20 points, the Chief selects exactly four of all seven.

## Scope

- No database migration.
- No Staging or Production deployment.
