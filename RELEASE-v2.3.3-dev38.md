# BDC v2.3.3-dev38

## Special Category Scoring

- Adds a dedicated Special Categories scoring workflow for Bachata Rising, Bachata Open and Bachata Invitational.
- Bachata Rising uses fixed placement points: 1st 5, 2nd 4, 3rd 3, 4th 2, 5th 1.
- Bachata Open uses fixed placement points: 1st 5, 2nd 4, 3rd 3, 4th 2, 5th 1.
- Bachata Invitational uses fixed placement points: 1st 3, 2nd 2, 3rd 1.
- Participant-count BDC point tiers do not determine special-category points.
- Special-category publication resolves every award into the competitor's role-specific BDC Novice, Intermediate or Advanced progression bucket.
- The participant result retains the actual special category while the point transaction remains in the standard BDC progression division.
- Special-category publication includes preview, Super Admin approval, repository publication and rollback support.

## BDC Identity

- Existing competitors retain their existing BDC ID.
- A genuinely new competitor receives the normal next BDC ID using the existing BDC-###### sequence.
- No Rising, Open or Invitational-specific competitor ID is created.
- New provisional competitors begin in the normal Novice progression bucket.

## Active Scoring Scope

- New standard scoring setup is limited to Novice, Intermediate and Advanced.
- Semi-Pro, Pro and Bachata All Star are not activated in the scoring engine in this release.
- Existing historical higher-division data and compatibility logic are preserved.
- Special categories use the existing Manual Scoring and Relative Placement workflow in this release.
- Existing Novice, Intermediate and Advanced Automatic Scoring remains unchanged.

## Data Compatibility

- Special category enum compatibility is added only where the competition category must be retained: scoring rounds, Registration Desk links/activity, scoring publications and participant results.
- BDC point transaction divisions remain standard progression divisions so leaderboard and promotion calculations continue to use Novice, Intermediate and Advanced points.
- No replacement competitor identity system is introduced.

## Deployment Safety

- No Production or Staging deployment is included in this release.
- `config/config.php` is untouched.
- No Production data is modified by this development release.
